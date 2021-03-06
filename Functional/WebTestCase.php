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
     * @todo [BC Break] Replace the non-static property with this one.
     * @var Client
     */
    private static $_client;

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
        try {
            $this->client = self::$_client = parent::createClient(
                $this->getKernelOptions(),
                $this->getServerParameters()
            );

            static::$application = new Application(static::$kernel);
            static::$application->setAutoExit(false);
            $this->container = static::$kernel->getContainer();

        }
        catch (Exception $e) {
            throw new RuntimeException(sprintf(
                'Unable to start the application: %s',
                get_class($e).':'.$e->getMessage()
            ));
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
     * @return array
     */
    protected function getKernelOptions()
    {
        return array();
    }

    /**
     * @return array An array of server parameters to pass to the test client
     */
    protected function getServerParameters()
    {
        return array();
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

    protected static function getBundleAndTestCaseName()
    {
        $return = array(
            'bundleName' => '',
            'bundleNamespace' => '',
            'testCaseName' => 'Default',
        );

        $appKernelLoc = '\\app\\AppKernel';
        $namespaceExploded = explode('\\Tests\\Functional\\', get_called_class(), 2);

        $return['bundleNamespace'] = $namespaceExploded[0];
        $bundleNamespaceParts = explode('\\', $namespaceExploded[0]);
        $return['bundleName'] = array_shift($bundleNamespaceParts).array_pop($bundleNamespaceParts);

        $parts = explode('\\', $namespaceExploded[1]);
        if (count($parts) > 1) {
            $return['testCaseName'] = array_shift($parts);
        }

        return $return;
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
        $testInfo = self::getBundleAndTestCaseName();

        $namespaceExploded = explode('\\Tests\\Functional\\', get_called_class(), 2);

        $baseNamespace = $testInfo['bundleNamespace'].'\\Tests\\Functional';

        if (isset($testInfo['testCaseName']) &&
            class_exists($baseNamespace.'\\'.$testInfo['testCaseName'].'\\app\\AppKernel')
        ) {
            $baseNamespace .= '\\'.$testInfo['testCaseName'];
        }

        $kernelClass = $baseNamespace . '\\app\\AppKernel';

        return $kernelClass;
    }

    /**
     * Creates a Client.
     *
     * @param array $options An array of options to pass to the createKernel class
     * @param array $server  An array of server parameters
     *
     * @return Client A Client instance
     */
    protected static function createClient(array $options = array(), array $server = array())
    {
        return self::$_client;
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
        $testInfo = self::getBundleAndTestCaseName();
        $env = $testInfo['bundleName'];
        if (!empty($testInfo['testCaseName'])) {
            $env .= '_'.$testInfo['testCaseName'];
        }
        else {
            $env .= 'Test';
        }

        static::$class = static::getKernelClass();
        return new static::$class($env, true, $testInfo);
    }
}
