<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\Test\Service;

use FM\ElfinderBundle\Connector\ElFinderConnector;
use FM\ElfinderBundle\Loader\ElFinderLoader;
use Mautic\CoreBundle\Helper\PathsHelper;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class LocalFileAdapterServiceTest extends MauticMysqlTestCase
{
    /**
     * @var string
     */
    private $folderName;

    protected function beforeTearDown(): void
    {
        $pathsHelper = $this->container->get('mautic.helper.paths');
        $folderPath  = "{$pathsHelper->getImagePath()}/{$this->folderName}";

        if (is_dir($folderPath)) {
            rmdir($folderPath);
        }
    }

    public function testElfinderCreateFolderPermissions(): void
    {
        $elFinderLoader = new class($this->container) extends ElFinderLoader {
            public function __construct(ContainerInterface $container)
            {
                parent::__construct($container->get('fm_elfinder.configurator'));
            }

            public function load(Request $request)
            {
                $connector = new ElFinderConnector($this->bridge);

                return $connector->execute($request->query->all());
            }
        };

        $this->container->set('fm_elfinder.loader', $elFinderLoader);

        $this->folderName = (string) time();
        $this->loginUser('admin');
        $this->client->request(
            Request::METHOD_POST,
            "efconnect?cmd=mkdir&name={$this->folderName}&target=fls1_Lw"
        );
        /** @var PathsHelper $pathsHelper */
        $pathsHelper = $this->container->get('mautic.helper.paths');
        $folderPath  = "{$pathsHelper->getImagePath()}/{$this->folderName}";
        self::assertDirectoryExists($folderPath);
        self::assertSame('777', substr(sprintf('%o', fileperms($folderPath)), -3));
    }
}
