<?php
namespace MOC\Varnish\Aspects;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Aop\JoinPointInterface;
use TYPO3\Flow\Reflection\ObjectAccess;
use TYPO3\TypoScript\Core\Runtime;

/**
 * Advice the RuntimeContentCache to check for uncached segments that should prevent caching
 *
 * @Flow\Aspect
 * @Flow\Scope("singleton")
 */
class ContentCacheAspect {

	/**
	 * @var array
	 */
	protected $settings = array();

	public function injectSettings(array $settings) {
			$this->settings = $settings;
	}

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
	 * @Flow\AfterReturning("method(TYPO3\TypoScript\Core\Cache\RuntimeContentCache->postProcess())")
	 * @param JoinPointInterface $joinPoint
	 */
	public function registerCreateUncached(JoinPointInterface $joinPoint) {
		if ($this->settings['disabled'] == FALSE) {
			$evaluateContext = $joinPoint->getMethodArgument('evaluateContext');

			$proxy = $joinPoint->getProxy();
			/** @var Runtime $runtime */
			$runtime = ObjectAccess::getProperty($proxy, 'runtime', TRUE);

			if ($evaluateContext['cacheForPathDisabled']) {
				$mocVarnishIgnoreUncached = $runtime->evaluate($evaluateContext['typoScriptPath'] . '/__meta/cache/mocVarnishIgnoreUncached');
				if ($mocVarnishIgnoreUncached !== TRUE) {
					$this->logger->log(sprintf('Varnish cache disabled due to uncached path "%s" (can be prevented using "mocVarnishIgnoreUncached")', $evaluateContext['typoScriptPath']), LOG_DEBUG);
					$this->evaluatedUncached = TRUE;
				}
			}
		}
	}

	/**
	 * Advice for uncached segments when rendering from a cached version
	 *
	 * @Flow\AfterReturning("method(TYPO3\TypoScript\Core\Cache\RuntimeContentCache->evaluateUncached())")
	 * @param JoinPointInterface $joinPoint
	 */
	public function registerEvaluateUncached(JoinPointInterface $joinPoint) {
		if ($this->settings['disabled'] == FALSE) {
			$path = $joinPoint->getMethodArgument('path');

			$proxy = $joinPoint->getProxy();
			/** @var Runtime $runtime */
			$runtime = ObjectAccess::getProperty($proxy, 'runtime', TRUE);

			$mocVarnishIgnoreUncached = $runtime->evaluate($path . '/__meta/cache/mocVarnishIgnoreUncached');
			if ($mocVarnishIgnoreUncached !== TRUE) {
				$this->logger->log(sprintf('Varnish cache disabled due to uncached path "%s" (can be prevented using "mocVarnishIgnoreUncached")', $path . '/__meta/cache/mocVarnishIgnoreUncached'), LOG_DEBUG);
				$this->evaluatedUncached = TRUE;
			}
		}
	}

	/**
	 * Advice for a disabled content cache (e.g. because an exception was handled)
	 *
	 * @Flow\AfterReturning("method(TYPO3\TypoScript\Core\Cache\RuntimeContentCache->setEnableContentCache())")
	 * @param JoinPointInterface $joinPoint
	 */
	public function registerDisableContentCache(JoinPointInterface $joinPoint) {
		if ($this->settings['disabled'] == FALSE) {
			$enableContentCache = $joinPoint->getMethodArgument('enableContentCache');
			if ($enableContentCache !== TRUE) {
				$this->logger->log('Varnish cache disabled due content cache being disabled (e.g. because an exception was handled)', LOG_DEBUG);
				$this->evaluatedUncached = TRUE;
			}
		}
	}

	/**
	 * @return boolean TRUE if an uncached segment was evaluated
	 */
	public function isEvaluatedUncached() {
		return $this->evaluatedUncached;
	}

}