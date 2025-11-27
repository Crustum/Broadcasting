<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Registry;

use BadMethodCallException;
use Cake\Core\App;
use Cake\Core\Exception\CakeException;
use Cake\Core\ObjectRegistry;
use Crustum\Broadcasting\Broadcaster\BroadcasterInterface;

/**
 * An object registry for broadcaster engines.
 *
 * Used by {@link \Crustum\Broadcasting\Broadcasting} to load and manage broadcaster engines.
 *
 * @extends \Cake\Core\ObjectRegistry<\Crustum\Broadcasting\Broadcaster\BroadcasterInterface>
 */
class BroadcasterRegistry extends ObjectRegistry
{
    /**
     * Resolve a broadcaster class name.
     *
     * Part of the template method for Cake\Core\ObjectRegistry::load()
     *
     * @param string $class Partial classname to resolve.
     * @return class-string<\Crustum\Broadcasting\Broadcaster\BroadcasterInterface>|null Either the correct classname or null.
     */
    protected function _resolveClassName(string $class): ?string
    {
        /** @var class-string<\Crustum\Broadcasting\Broadcaster\BroadcasterInterface>|null */
        return App::className($class, 'Broadcaster', 'Broadcaster');
    }

    /**
     * Throws an exception when a broadcaster is missing.
     *
     * Part of the template method for Cake\Core\ObjectRegistry::load()
     *
     * @param string $class The classname that is missing.
     * @param string|null $plugin The plugin the broadcaster is missing in.
     * @return void
     * @throws \BadMethodCallException
     */
    protected function _throwMissingClassError(string $class, ?string $plugin): void
    {
        throw new BadMethodCallException(sprintf('Broadcaster engine `%s` is not available.', $class));
    }

    /**
     * Create the broadcaster instance.
     *
     * Part of the template method for Cake\Core\ObjectRegistry::load()
     *
     * @param \Crustum\Broadcasting\Broadcaster\BroadcasterInterface|class-string<\Crustum\Broadcasting\Broadcaster\BroadcasterInterface> $class The classname or object to make.
     * @param string $alias The alias of the object.
     * @param array<string, mixed> $config An array of settings to use for the broadcaster.
     * @return \Crustum\Broadcasting\Broadcaster\BroadcasterInterface The constructed BroadcasterInterface class.
     * @throws \Cake\Core\Exception\CakeException When the broadcaster cannot be initialized.
     */
    protected function _create(object|string $class, string $alias, array $config): BroadcasterInterface
    {
        if (is_object($class)) {
            $instance = $class;
        } else {
            $instance = new $class($config);
        }
        unset($config['className']);

        if (!$instance->getConfig()) {
            throw new CakeException(
                sprintf(
                    'Broadcaster `%s` is not properly configured. Check error log for additional information.',
                    $instance::class,
                ),
            );
        }

        return $instance;
    }

    /**
     * Remove a single broadcaster from the registry.
     *
     * @param string $name The broadcaster name.
     * @return $this
     */
    public function unload(string $name)
    {
        unset($this->_loaded[$name]);

        return $this;
    }
}
