<?php

namespace Bazinga\Bundle\PropelEventDispatcherBundle\Injector;

use Bazinga\Bundle\PropelEventDispatcherBundle\EventDispatcher\LazyEventDispatcher;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DispatcherInjector
{
	const MODEL_INTERFACE = 'EventDispatcherAwareModelInterface';

	private $classes;

	private $container;

	private $logger;

	public function __construct(ContainerInterface $container, array $classes, LoggerInterface $logger = null)
	{
		$this->classes   = $classes;
		$this->container = $container;
		$this->logger    = $logger;
	}

	/**
	 * Initializes the EventDispatcher-aware models.
	 */
	public function initializeModels()
	{
		$self = $this;
		$this->setEventDispatcherOnModels(function ($id) use ($self) {
			return new LazyEventDispatcher($self->container, $id);
		});
	}

	/**
	 * Uninitializes the EventDispatcher-aware models.
	 */
	public function uninitializeModels()
	{
		$this->setEventDispatcherOnModels(function () {
			return new EventDispatcher();
		});
	}

	/**
	 * Sets the event dispatcher on the EventDispatcher-aware models.
	 *
	 * This methods has to accept unknown classes as it is triggered during
	 * the boot and so will be called before running the propel:build command.
	 */
	protected function setEventDispatcherOnModels($getDispatcher)
	{
		foreach ($this->classes as $id => $class) {
			$baseClass = sprintf(
				'%s\\Base\\%s',
				substr($class, 0, strrpos($class, '\\')),
				substr($class, strrpos($class, '\\') + 1, strlen($class))
			);

			try {
				$ref = new \ReflectionClass($baseClass);
			} catch (\ReflectionException $e) {
				$this->log(sprintf('The class "%s" does not exist.', $baseClass));

				continue;
			}

			try {
				$ref = new \ReflectionClass($class);
			} catch (\ReflectionException $e) {
				$this->log(
					sprintf(
						'The class "%s" does not exist. Either your model is not generated yet or you have an error in your listener configuration.',
                    $class
                ));

                continue;
            }

            if (!$ref->implementsInterface(self::MODEL_INTERFACE)) {
                $this->log(sprintf(
                    'The class "%s" does not implement "%s". Either your model is outdated or you forgot to add the EventDispatcherBehavior.',
                    $class,
                    self::MODEL_INTERFACE
                ));

                continue;
            }

			$class::setEventDispatcher($getDispatcher($id));
        }
    }

    private function log($message)
    {
        if (null !== $this->logger) {
            $this->logger->warn($message);
        }
    }
}
