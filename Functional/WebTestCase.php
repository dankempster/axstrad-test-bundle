<?php
/**
 * This file is part of the Elcodi package.
 *
 * Copyright (c) 2014 Elcodi.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Feel free to edit as you please, and have fun.
 *
 * @author Dan Kempster <dev@dankempster.co.uk>
 */
namespace Axstrad\Bundle\TestBundle\Functional;

use Axstrad\Component\Test\Console\Output\BufferedOutput;
use Exception;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as BaseWebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\HttpKernel\KernelInterface;


/**
 * Axstrad\Bundle\TestBundle\Functional\WebTestCase
 */
abstract class WebTestCase extends BaseWebTestCase
{
    /**
     * @var Application
     *
     * application
     */
    protected static $application;

    /**
     * @var Client
     *
     * Client
     */
    protected $client;

    /**
     * @var ContainerInterface
     *
     * Container
     */
    protected $container;

    /**
     * Set up
     */
    public function setUp()
    {
        gc_collect_cycles();

        try {
            static::$kernel = static::createKernel();
            static::$kernel->boot();

            static::$application = new Application(static::$kernel);
            static::$application->setAutoExit(false);
            $this->container = static::$kernel->getContainer();

        } catch (Exception $e) {

            throw new RuntimeException(sprintf('Unable to start the application: %s', $e->getMessage()));
        }

        $this->createSchema();
    }

    /**
     * Tear down
     */
    public function tearDown()
    {
        if (!static::$application) return;

        $output = new BufferedOutput();

        static::$application->run(new ArrayInput(array(
            'command'          => 'doctrine:database:drop',
            '--no-interaction' => true,
            '--force'          => true,
            '--quiet'          => true,
        )), $output);

        if (!$this->runCommandsQuietly()) {
            echo $output->fetch();
        }

        parent::tearDown();
    }

    /**
     * Load fixtures of these bundles
     *
     * @return boolean|array Bundles name where fixtures should be found
     */
    protected function loadBundlesFixtures()
    {
        return false;
    }

    /**
     * Schema must be loaded in all test cases
     *
     * @return boolean Load schema
     */
    protected function loadSchema()
    {
        return true;
    }

    /**
     * Should schema be loaded quietly
     */
    protected function runCommandsQuietly()
    {
        return true;
    }

    /**
     * Creates schema
     *
     * Only creates schema if loadSchema() is set to true.
     * All other methods will be loaded if this one is loaded.
     *
     * Otherwise, will return.
     *
     * @return $this self Object
     */
    protected function createSchema()
    {
        if (!$this->loadSchema()) {
            return $this;
        }

        $output = new BufferedOutput();

        static::$application->run(new ArrayInput(array(
            'command'          => 'doctrine:database:drop',
            '--no-interaction' => true,
            '--force'          => true,
        )), $output);

        static::$application->run(new ArrayInput(array(
            'command'          => 'doctrine:database:create',
            '--no-interaction' => true,
        )), $output);

        static::$application->run(new ArrayInput(array(
            'command'          => 'doctrine:schema:create',
            '--no-interaction' => true,
        )), $output);

        if (!$this->runCommandsQuietly()) {
            echo $output->fetch();
        }

        $this->loadFixtures();

        return $this;
    }

    /**
     * load fixtures method
     *
     * This method is only called if create Schema is set to true
     *
     * Only load fixtures if loadFixtures() is set to true.
     * All other methods will be loaded if this one is loaded.
     *
     * Otherwise, will return.
     *
     * @return $this self Object
     */
    protected function loadFixtures()
    {
        if (!is_array($this->loadBundlesFixtures())) {
            return $this;
        }

        $bundles = static::$kernel->getBundles();
        $formattedBundles = array_map(function ($bundle) use ($bundles) {
            return $bundles[$bundle]->getPath() . '/DataFixtures/ORM/';
        }, $this->loadBundlesFixtures());

        $output = new BufferedOutput();

        self::$application->run(new ArrayInput(array(
            'command'          => 'doctrine:fixtures:load',
            '--no-interaction' => true,
            '--fixtures'       => $formattedBundles,
        )), $output);

        if (!$this->runCommandsQuietly()) {
            echo $output->fetch();
        }

        return $this;
    }

    /**
     * Attempts to guess the kernel location.
     *
     * When the Kernel is located, the file is required.
     *
     * @return string The Kernel class name
     *
     * @throws \RuntimeException
     */
    protected static function getKernelClass()
    {
        $namespaceExploded = explode('\\Tests\\Functional\\', get_called_class(), 2);
        $kernelClass = $namespaceExploded[0] . '\\Tests\\Functional\\app\\AppKernel';

        return $kernelClass;
    }

    /**
     * Creates a Kernel.
     *
     * Available options:
     *
     *  * environment
     *  * debug
     *
     * @param array $options An array of options
     *
     * @return KernelInterface A KernelInterface instance
     */
    protected static function createKernel(array $options = array())
    {
        static::$class = static::getKernelClass();

        $namespaceExploded = explode('\\Tests\\Functional\\', get_called_class(), 2);
        $bundleName = explode('Axstrad\\', $namespaceExploded[0], 2)[1];
        $bundleName = str_replace('\\', '_', $bundleName);

        return new static::$class($bundleName . 'Test', true);
    }
}
