<?php
declare(strict_types=1);

namespace MOC\Varnish\Aspects;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Flow\Configuration\Exception\InvalidConfigurationException;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Fusion\Exception;
use Neos\Fusion\Exception\RuntimeException;
use Neos\Utility\Exception\PropertyNotAccessibleException;
use Neos\Utility\ObjectAccess;
use Neos\Fusion\Core\Runtime;
use Psr\Log\LoggerInterface;

/**
 * Advice the RuntimeContentCache to check for uncached segments that should prevent caching
 *
 * @Flow\Aspect
 * @Flow\Scope("singleton")
 */
class ContentCacheAspect
{

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var bool
     */
    protected $evaluatedUncached = false;

    /**
     * Advice for uncached segments when rendering the initial output (without replacing an uncached marker in cached output)
     *
     * @Flow\AfterReturning("setting(MOC.Varnish.enabled) && method(Neos\Fusion\Core\Cache\RuntimeContentCache->postProcess())")
     * @param JoinPointInterface $joinPoint
     * @throws PropertyNotAccessibleException
     * @throws InvalidConfigurationException
     * @throws StopActionException
     * @throws \Neos\Flow\Security\Exception
     * @throws Exception
     * @throws RuntimeException
     */
    public function registerCreateUncached(JoinPointInterface $joinPoint): void
    {
        $evaluateContext = $joinPoint->getMethodArgument('evaluateContext');

        $proxy = $joinPoint->getProxy();
        /** @var Runtime $runtime */
        $runtime = ObjectAccess::getProperty($proxy, 'runtime', true);

        if ($evaluateContext['cacheForPathDisabled']) {
            $mocVarnishIgnoreUncached = $runtime->evaluate($evaluateContext['fusionPath'] . '/__meta/cache/mocVarnishIgnoreUncached');
            if ($mocVarnishIgnoreUncached !== true) {
                $this->logger->debug(sprintf('Varnish cache disabled due to uncached path "%s" (can be prevented using "mocVarnishIgnoreUncached")', $evaluateContext['fusionPath']), LogEnvironment::fromMethodName(__METHOD__));
                $this->evaluatedUncached = true;
            }
        }
    }

    /**
     * Advice for uncached segments when rendering from a cached version
     *
     * @Flow\AfterReturning("setting(MOC.Varnish.enabled) && method(Neos\Fusion\Core\Cache\RuntimeContentCache->evaluateUncached())")
     * @param JoinPointInterface $joinPoint
     * @throws PropertyNotAccessibleException
     * @throws InvalidConfigurationException
     * @throws StopActionException
     * @throws \Neos\Flow\Security\Exception
     * @throws Exception
     * @throws RuntimeException
     */
    public function registerEvaluateUncached(JoinPointInterface $joinPoint): void
    {
        $path = $joinPoint->getMethodArgument('path');

        $proxy = $joinPoint->getProxy();
        /** @var Runtime $runtime */
        $runtime = ObjectAccess::getProperty($proxy, 'runtime', true);

        $mocVarnishIgnoreUncached = $runtime->evaluate($path . '/__meta/cache/mocVarnishIgnoreUncached');
        if ($mocVarnishIgnoreUncached !== true) {
            $this->logger->debug(sprintf('Varnish cache disabled due to uncached path "%s" (can be prevented using "mocVarnishIgnoreUncached")', $path . '/__meta/cache/mocVarnishIgnoreUncached'), LogEnvironment::fromMethodName(__METHOD__));
            $this->evaluatedUncached = true;
        }
    }

    /**
     * Advice for a disabled content cache (e.g. because an exception was handled)
     *
     * @Flow\AfterReturning("setting(MOC.Varnish.enabled) && method(Neos\Fusion\Core\Cache\RuntimeContentCache->setEnableContentCache())")
     * @param JoinPointInterface $joinPoint
     */
    public function registerDisableContentCache(JoinPointInterface $joinPoint): void
    {
        $enableContentCache = $joinPoint->getMethodArgument('enableContentCache');
        if ($enableContentCache !== true) {
            $this->logger->debug('Varnish cache disabled due content cache being disabled (e.g. because an exception was handled)', LogEnvironment::fromMethodName(__METHOD__));
            $this->evaluatedUncached = true;
        }
    }

    /**
     * @return bool true if an uncached segment was evaluated
     */
    public function isEvaluatedUncached(): bool
    {
        return $this->evaluatedUncached;
    }
}
