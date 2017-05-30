<?php

namespace Mediact\TestingSuite\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Mediact\Composer\DependencyInstaller\DependencyInstaller;
use Mediact\Composer\FileInstaller;
use Mediact\FileMapping\UnixFileMappingReader;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    /** @var Composer */
    private $composer;

    /** @var FileInstaller */
    private $fileInstaller;

    /** @var DependencyInstaller */
    private $dependencyInstaller;

    /** @var string[] */
    private $codingStandardsMapping = [
        'magento2-module' => 'magento2',
        'magento-module' => 'magento1'
    ];

    /** @var array */
    private $repositoryMapping = [
        'default' => [],
        'magento1' => [
            [
                'name' => 'magento',
                'type' => 'composer',
                'url' => 'https://repo.magento.com'
            ]
        ],
        'magento2' => [
            [
                'name' => 'magento',
                'type' => 'composer',
                'url' => 'https://repo.magento.com'
            ]
        ]
    ];

    /** @var array */
    private $packageMapping = [
        'default' => [],
        'magento1' => [
            [
                'name' => 'mediact/coding-standard-magento1',
                'version' => '@stable'
            ]
        ],
        'magento2' => [
            [
                'name' => 'mediact/coding-standard-magento2',
                'version' => '@stable'
            ]
        ],
    ];

    /**
     * Apply plugin modifications to Composer.
     *
     * @param Composer    $composer
     * @param IOInterface $io
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer            = $composer;
        $this->dependencyInstaller = new DependencyInstaller();
        $this->fileInstaller       = new FileInstaller(
            new UnixFileMappingReader(
                __DIR__ . '/../templates/files',
                getcwd(),
                ...$this->getFilePaths()
            )
        );
    }

    /**
     * Install the default configuration files.
     *
     * @param Event $event
     *
     * @return void
     */
    public function installFiles(Event $event)
    {
        $this->fileInstaller->install($event->getIO());
    }

    /**
     * Install the required repositories.
     *
     * @return void
     */
    public function installRepositories()
    {
        $type = $this->getType();

        foreach ($this->repositoryMapping[$type] as $repository) {
            $this->dependencyInstaller->installRepository(
                $repository['name'],
                $repository['type'],
                $repository['url']
            );
        }
    }

    /**
     * Install the required packages.
     *
     * @return void
     */
    public function installPackages()
    {
        $type = $this->getType();

        foreach ($this->packageMapping[$type] as $package) {
            $this->dependencyInstaller->installPackage(
                $package['name'],
                $package['version']
            );
        }
    }

    /**
     * Subscribe to post update and post install command.
     *
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'post-install-cmd' => [
                ['installFiles'],
                ['installPackages', 1],
                ['installRepositories', 2]
            ],
            'post-update-cmd' => [
                ['installFiles'],
                ['installPackages', 1],
                ['installRepositories', 2]
            ]
        ];
    }

    /**
     * @return string[]
     */
    private function getFilePaths(): array
    {
        return [
            __DIR__ . '/../templates/mapping/files',
            $this->getPhpCsMappingFile()
        ];
    }

    /**
     * @return string
     */
    private function getPhpCsMappingFile(): string
    {
        return __DIR__ . '/../templates/mapping/phpcs/' . $this->getType();
    }

    /**
     * @return string
     */
    private function getType(): string
    {
        $packageType = $this->composer->getPackage()->getType();

        return array_key_exists($packageType, $this->codingStandardsMapping)
            ? $this->codingStandardsMapping[$packageType]
            : 'default';
    }
}
