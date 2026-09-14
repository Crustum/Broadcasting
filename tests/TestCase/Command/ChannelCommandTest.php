<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Command;

use Cake\Console\Arguments;
use Cake\Console\CommandInterface;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use Crustum\Broadcasting\Command\ChannelCommand;
use ReflectionClass;
use TestApp\Application;
use TestApp\Model\Entity\User;

/**
 * ChannelCommand Test
 *
 * Tests for the ChannelCommand bake command.
 */
class ChannelCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Router::reload();
        $this->setAppNamespace('TestApp');
        $this->configApplication(Application::class, [CONFIG]);

        $this->loadPlugins([
            'Bake',
        ]);
    }

    /**
     * Clean up generated files after tests
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $files = [
            APP . 'Broadcasting/OrderChannel.php',
            APP . 'Broadcasting/MyChannel.php',
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Test execute with valid channel name
     *
     * @return void
     */
    public function testMain(): void
    {
        $this->exec('bake channel --force Order');

        $this->assertExitCode(CommandInterface::CODE_SUCCESS);
        $file = APP . 'Broadcasting/OrderChannel.php';
        $this->assertFileExists($file);
        $contents = file_get_contents($file);
        $this->assertIsString($contents);
        $this->assertStringContainsString('class OrderChannel', $contents);
        $this->assertStringContainsString('ChannelInterface', $contents);
    }

    /**
     * Test channel name suffix is added automatically
     *
     * @return void
     */
    public function testChannelSuffixAddition(): void
    {
        $this->exec('bake channel --force My');

        $this->assertExitCode(CommandInterface::CODE_SUCCESS);
        $file = APP . 'Broadcasting/MyChannel.php';
        $this->assertFileExists($file);
        $contents = file_get_contents($file);
        $this->assertIsString($contents);
        $this->assertStringContainsString('class MyChannel', $contents);
    }

    /**
     * Test execute with missing channel name returns error
     *
     * @return void
     */
    public function testMissingName(): void
    {
        $this->exec('bake channel');

        $this->assertExitCode(CommandInterface::CODE_ERROR);
    }

    /**
     * Test getChannelNameFromClass
     *
     * @return void
     */
    public function testGetChannelNameFromClass(): void
    {
        $command = new ChannelCommand();
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('getChannelNameFromClass');

        $this->assertEquals('order', $method->invoke($command, 'OrderChannel'));
        $this->assertEquals('user-notification', $method->invoke($command, 'UserNotificationChannel'));
    }

    /**
     * Test getUserModel with default
     *
     * @return void
     */
    public function testGetUserModelDefault(): void
    {
        Configure::delete('Broadcasting.user_model');

        $command = new ChannelCommand();
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('getUserModel');

        $this->assertEquals('User', $method->invoke($command));
    }

    /**
     * Test getUserModel with configuration
     *
     * @return void
     */
    public function testGetUserModelWithConfig(): void
    {
        Configure::write('Broadcasting.user_model', 'App\Model\Entity\CustomUser');

        $command = new ChannelCommand();
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('getUserModel');

        $this->assertEquals('CustomUser', $method->invoke($command));

        Configure::delete('Broadcasting.user_model');
    }

    /**
     * Test getNamespacedUserModel with default
     *
     * @return void
     */
    public function testGetNamespacedUserModelDefault(): void
    {
        Configure::delete('Broadcasting.user_model');

        $command = new ChannelCommand();
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('getNamespacedUserModel');

        $this->assertEquals(User::class, $method->invoke($command));
    }

    /**
     * Test getNamespacedUserModel with configuration
     *
     * @return void
     */
    public function testGetNamespacedUserModelWithConfig(): void
    {
        Configure::write('Broadcasting.user_model', 'App\Model\Entity\CustomUser');

        $command = new ChannelCommand();
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('getNamespacedUserModel');

        $this->assertEquals('App\Model\Entity\CustomUser', $method->invoke($command));

        Configure::delete('Broadcasting.user_model');
    }

    /**
     * Test getPath
     *
     * @return void
     */
    public function testGetPath(): void
    {
        $command = new ChannelCommand();
        $args = $this->createStub(Arguments::class);
        $args->method('getOption')->willReturn(null);

        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('getPath');

        $path = $method->invoke($command, $args);
        $this->assertStringContainsString('Broadcasting', $path);
    }
}
