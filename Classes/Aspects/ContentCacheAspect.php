<?php
namespace MOC\Varnish\Aspects;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Utility\ObjectAccess;
use Neos\Fusion\Core\Runtime;

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
     * @var \MOC\Varnish\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var boolean
     */
    protected $evaluatedUncached;

    /**
     * Advice for uncached segments when rendering the initial output (without replacing an uncached marker in cached output)
     *
     * @Flow\AfterReturning("setting(MOC.Varnish.enabled) && method(Neos\Fusion\Core\Cache\RuntimeContentCache->postProcess())")
     * @param JoinPointInterface $joinPoint
     */
    public function registerCreateUncached(JoinPointInterface $joinPoint)
    {
        $evaluateContext = $joinPoint->getMethodArgument('evaluateContext');

        $proxy = $joinPoint->getProxy();
        /** @var Runtime $runtime */
        $runtime = ObjectAccess::getProperty($proxy, 'runtime', true);

        if ($evaluateContext['cacheForPathDisabled']) {
            $mocVarnishIgnoreUncached = $runtime->evaluate($evaluateContext['typoScriptPath'] . '/__meta/cache/mocVarnishIgnoreUncached');
            if ($mocVarnishIgnoreUncached !== true) {
                $this->logger->log(sprintf('Varnish cache disabled due to uncached path "%s" (can be prevented using "mocVarnishIgnoreUncached")', $evaluateContext['typoScriptPath']), LOG_DEBUG);
                $this->evaluatedUncached = true;
            }
        }
    }

    /**
     * Advice for uncached segments when rendering from a cached version
     *
     * @Flow\AfterReturning("setting(MOC.Varnish.enabled) && method(Neos\Fusion\Core\Cache\RuntimeContentCache->evaluateUncached())")
     * @param JoinPointInterface $joinPoint
     */
    public function registerEvaluateUncached(JoinPointInterface $joinPoint)
    {
        $path = $joinPoint->getMethodArgument('path');

        $proxy = $joinPoint->getProxy();
        /** @var Runtime $runtime */
        $runtime = ObjectAccess::getProperty($proxy, 'runtime', true);

        $mocVarnishIgnoreUncached = $runtime->evaluate($path . '/__meta/cache/mocVarnishIgnoreUncached');
        if ($mocVarnishIgnoreUncached !== true) {
            $this->logger->log(sprintf('Varnish cache disabled due to uncached path "%s" (can be prevented using "mocVarnishIgnoreUncached")', $path . '/__meta/cache/mocVarnishIgnoreUncached'), LOG_DEBUG);
            $this->evaluatedUncached = true;
        }
    }

    /**
     * Advice for a disabled content cache (e.g. because an exception was handled)
     *
     * @Flow\AfterReturning("setting(MOC.Varnish.enabled) && method(Neos\Fusion\Core\Cache\RuntimeContentCache->setEnableContentCache())")
     * @param JoinPointInterface $joinPoint
     */
    public function registerDisableContentCache(JoinPointInterface $joinPoint)
    {
        $enableContentCache = $joinPoint->getMethodArgument('enableContentCache');
        if ($enableContentCache !== true) {
            $this->logger->log('Varnish cache disabled due content cache being disabled (e.g. because an exception was handled)', LOG_DEBUG);
            $this->evaluatedUncached = true;
        }
    }

    /**
     * @return boolean TRUE if an uncached segment was evaluated
     */
    public function isEvaluatedUncached()
    {
        return $this->evaluatedUncached;
    }
}
