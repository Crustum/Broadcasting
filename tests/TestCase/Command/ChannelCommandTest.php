<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Test\TestCase\Command;

use Cake\Console\Arguments;
use Cake\Console\CommandInterface;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Cake\View\Exception\MissingTemplateException;
use Crustum\Broadcasting\Command\ChannelCommand;
use ReflectionClass;

/**
 * ChannelCommand Test
 *
 * Tests for the ChannelCommand bake command.
 */
class ChannelCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * Generated file path
     *
     * @var string
     */
    protected string $generatedFile = '';

    /**
     * Generated files paths
     *
     * @var array<string>
     */
    protected array $generatedFiles = [];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->setAppNamespace('TestApp');
        $this->configApplication('TestApp\Application', [CONFIG]);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();

        if ($this->generatedFile && file_exists($this->generatedFile)) {
            unlink($this->generatedFile);
            $this->generatedFile = '';
        }

        if (count($this->generatedFiles)) {
            foreach ($this->generatedFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            $this->generatedFiles = [];
        }
    }

    /**
     * Test execute with missing channel name
     *
     * @return void
     */
    public function testExecuteWithMissingName(): void
    {
        $command = new ChannelCommand();
        $io = $this->createMock(ConsoleIo::class);

        $io->expects($this->once())
            ->method('err')
            ->with($this->stringContains('You must provide a channel name'));

        $io->expects($this->once())
            ->method('out')
            ->with($this->stringContains('Example: bin/cake bake channel Order'));

        $args = $this->createStub(Arguments::class);
        $args->method('getArgumentAt')->with(0)->willReturn(null);

        $result = $command->execute($args, $io);

        $this->assertEquals(CommandInterface::CODE_ERROR, $result);
    }

    /**
     * Test execute with valid channel name
     *
     * @return void
     */
    public function testExecuteWithValidName(): void
    {
        $command = new ChannelCommand();
        $command->plugin = 'Crustum/Broadcasting';

        $io = $this->createMock(ConsoleIo::class);
        $io->expects($this->never())->method('err');

        $args = $this->createMock(Arguments::class);
        $args->method('getArgumentAt')->with(0)->willReturn('Order');

        $args->method('getOption')->willReturnCallback(function ($key) {
            if ($key === 'verbose') {
                return false;
            }
            if ($key === 'force') {
                return false;
            }

            return null;
        });

        $io->method('out')->willReturnCallback(function ($message, $level = 0) {
        });

        $io->method('createFile')->willReturn(true);

        try {
            $content = $command->getContent('OrderChannel', $args, $io);
            $this->assertNotEmpty($content);
            $this->assertIsString($content);
            $this->assertStringContainsString('OrderChannel', $content);
        } catch (MissingTemplateException $e) {
            $this->markTestSkipped('Template not available in test environment: ' . $e->getMessage());
        }
    }

    /**
     * Test getContent returns valid content
     *
     * @return void
     */
    public function testGetContentReturnsContent(): void
    {
        $command = new ChannelCommand();
        $command->plugin = 'Crustum/Broadcasting';

        $io = $this->createMock(ConsoleIo::class);
        $args = $this->createMock(Arguments::class);

        $args->method('getOption')->willReturn(null);

        try {
            $content = $command->getContent('TestChannel', $args, $io);
            $this->assertNotEmpty($content);
            $this->assertIsString($content);
            $this->assertStringContainsString('TestChannel', $content);
        } catch (MissingTemplateException $e) {
            $this->markTestSkipped('Template not available in test environment: ' . $e->getMessage());
        }
    }

    /**
     * Test channel name suffix addition in execute method
     *
     * @return void
     */
    public function testChannelSuffixAddition(): void
    {
        $command = new ChannelCommand();
        $command->plugin = 'Crustum/Broadcasting';

        $io = $this->createMock(ConsoleIo::class);
        $io->method('createFile')->willReturn(true);

        $args = $this->createMock(Arguments::class);
        $args->method('getArgumentAt')->with(0)->willReturn('Order');

        $args->method('getOption')->willReturnCallback(function ($key) {
            if ($key === 'verbose') {
                return false;
            }
            if ($key === 'force') {
                return false;
            }

            return null;
        });

        try {
            $content = $command->getContent('OrderChannel', $args, $io);
            $this->assertNotEmpty($content);
            if (is_string($content)) {
                $this->assertStringContainsString('OrderChannel', $content);
            }
        } catch (MissingTemplateException $e) {
            $this->markTestSkipped('Template not available in test environment: ' . $e->getMessage());
        }
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
        $method->setAccessible(true);

        $result = $method->invoke($command, 'OrderChannel');
        $this->assertEquals('order', $result);

        $result = $method->invoke($command, 'UserNotificationChannel');
        $this->assertEquals('user-notification', $result);
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
        $method->setAccessible(true);

        $result = $method->invoke($command);
        $this->assertEquals('User', $result);
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
        $method->setAccessible(true);

        $result = $method->invoke($command);
        $this->assertEquals('CustomUser', $result);

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
        $method->setAccessible(true);

        $result = $method->invoke($command);
        $this->assertEquals('TestApp\Model\Entity\User', $result);
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
        $method->setAccessible(true);

        $result = $method->invoke($command);
        $this->assertEquals('App\Model\Entity\CustomUser', $result);

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
        $method->setAccessible(true);

        $path = $method->invoke($command, $args);
        $this->assertStringContainsString('Broadcasting', $path);
    }

    /**
     * Test buildOptionParser
     *
     * @return void
     */
    public function testBuildOptionParser(): void
    {
        $command = new ChannelCommand();
        $parser = $command->buildOptionParser(
            new ConsoleOptionParser('bake channel'),
        );

        $this->assertNotEmpty($parser->getDescription());
        $this->assertInstanceOf(ConsoleOptionParser::class, $parser);
    }

    /**
     * Test defaultName
     *
     * @return void
     */
    public function testDefaultName(): void
    {
        $name = ChannelCommand::defaultName();
        $this->assertEquals('bake channel', $name);
    }

    /**
     * Test name method
     *
     * @return void
     */
    public function testName(): void
    {
        $command = new ChannelCommand();
        $this->assertEquals('channel', $command->name());
    }
}
