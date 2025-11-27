<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Command;

use Bake\Command\BakeCommand;
use Bake\Utility\TemplateRenderer;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Utility\Inflector;

/**
 * Command for generating Channel authorization classes.
 *
 * Generates channel classes that implement ChannelInterface
 * for handling authorization logic for private and presence channels.
 *
 * Usage:
 * ```
 * bin/cake bake channel OrderChannel
 * bin/cake bake channel UserNotificationChannel
 * ```
 */
class ChannelCommand extends BakeCommand
{
    /**
     * Path to Channel directory
     *
     * @var string
     */
    public string $pathFragment = 'Broadcasting/';

    /**
     * Execute the command.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return int|null The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $name = $args->getArgumentAt(0);

        if (empty($name)) {
            $io->err('<error>You must provide a channel name.</error>');
            $io->out('Example: bin/cake bake channel Order');

            return static::CODE_ERROR;
        }

        $name = $this->_getName($name);

        // Ensure Channel suffix
        if (!str_ends_with($name, 'Channel')) {
            $name .= 'Channel';
        }

        $content = $this->getContent($name, $args, $io);

        if (empty($content)) {
            $io->err("<warning>No generated content for '{$name}', not generating template.</warning>");

            return static::CODE_ERROR;
        }

        $this->bake($name, $args, $io, $content);

        return static::CODE_SUCCESS;
    }

    /**
     * Assembles and writes bakes the channel file.
     *
     * @param string $name Channel name
     * @param \Cake\Console\Arguments $args CLI arguments
     * @param \Cake\Console\ConsoleIo $io Console io
     * @param string|true $content Content to write.
     * @return void
     */
    public function bake(string $name, Arguments $args, ConsoleIo $io, string|bool $content): void
    {
        $path = $this->getPath($args);
        $filename = $path . $name . '.php';
        $io->out("\n" . sprintf('Baking channel class for %s...', $name), 1, ConsoleIo::QUIET);

        if (is_string($content) && $args->getOption('verbose')) {
            $io->out($content);
        }

        if (is_string($content)) {
            $forceOption = $args->getOption('force');
            $force = is_bool($forceOption) ? $forceOption : false;
            $io->createFile($filename, $content, $force);
        }

        $emptyFile = $path . '.gitkeep';
        $this->deleteEmptyFile($emptyFile, $io);
    }

    /**
     * Get content for channel class.
     *
     * @param string $name Channel name
     * @param \Cake\Console\Arguments $args CLI arguments
     * @param \Cake\Console\ConsoleIo $io Console io
     * @return string|bool Generated content
     */
    public function getContent(string $name, Arguments $args, ConsoleIo $io): string|bool
    {
        $namespace = Configure::read('App.namespace');
        if ($this->plugin) {
            $namespace = $this->_pluginNamespace($this->plugin);
        }

        $channelName = $this->getChannelNameFromClass($name);
        $userModel = $this->getUserModel();
        $namespacedUserModel = $this->getNamespacedUserModel();

        $vars = [
            'namespace' => $namespace,
            'class' => $name,
            'channelName' => $channelName,
            'userModel' => $userModel,
            'namespacedUserModel' => $namespacedUserModel,
        ];

        $themeOption = $args->getOption('theme');
        $theme = is_string($themeOption) ? $themeOption : null;
        $renderer = new TemplateRenderer($theme);
        $renderer->set('plugin', $this->plugin);
        $renderer->set($vars);

        return $renderer->generate('Crustum/Broadcasting.Channel/channel');
    }

    /**
     * Gets the path for output. Checks the plugin property
     * and returns the correct path.
     *
     * @param \Cake\Console\Arguments $args Arguments instance to read the prefix option from.
     * @return string Path to output.
     */
    public function getPath(Arguments $args): string
    {
        $path = APP . $this->pathFragment;
        if ($this->plugin) {
            $path = $this->_pluginPath($this->plugin) . 'src/' . $this->pathFragment;
        }
        $prefix = $this->getPrefix($args);
        if ($prefix) {
            $path .= $prefix . DIRECTORY_SEPARATOR;
        }

        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Gets the option parser instance and configures it.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to configure
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = $this->_setCommonOptions($parser);
        $parser->setDescription('Bake Channel authorization class.')
            ->addArgument('name', [
                'help' => 'Name of the channel class to generate (e.g., Order, UserNotification). "Channel" suffix will be added automatically.',
                'required' => true,
            ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'bake channel';
    }

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'channel';
    }

    /**
     * Get the channel name from the class name.
     *
     * @param string $className Class name
     * @return string Channel name
     */
    protected function getChannelNameFromClass(string $className): string
    {
        $name = str_replace('Channel', '', $className);

        return Inflector::dasherize(Inflector::underscore($name));
    }

    /**
     * Get the user model class name.
     *
     * @return string User model class name
     */
    protected function getUserModel(): string
    {
        $userModel = Configure::read('Broadcasting.user_model');
        if ($userModel) {
            $parts = explode('\\', $userModel);

            return end($parts);
        }

        return 'User';
    }

    /**
     * Get the fully namespaced user model class name.
     *
     * @return string Namespaced user model class name
     */
    protected function getNamespacedUserModel(): string
    {
        $userModel = Configure::read('Broadcasting.user_model');
        if ($userModel) {
            return $userModel;
        }

        $namespace = Configure::read('App.namespace');

        return $namespace . '\\Model\\Entity\\User';
    }
}
