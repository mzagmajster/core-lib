<?php

declare(strict_types=1);

namespace Mautic\MarketplaceBundle\Tests\Functional\Controller;

use Mautic\CoreBundle\Helper\CacheHelper;
use Mautic\CoreBundle\Test\AbstractMauticTestCase;
use Mautic\MarketplaceBundle\Controller\AjaxController;
use Mautic\MarketplaceBundle\Model\ConsoleOutputModel;
use Mautic\MarketplaceBundle\Service\Composer;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;

final class AjaxControllerTest extends AbstractMauticTestCase
{
    public function testInstallPackageAction(): void
    {
        $request    = new Request([], [], [], [], [], [], '{"vendor":"mautic","package":"test-plugin-bundle"}');
        $controller = $this->generateController(false);

        $response = $controller->installPackageAction($request);

        Assert::assertSame('{"success":true}', $response->getContent());
        Assert::assertSame(200, $response->getStatusCode());
    }

    public function testRemovePackageAction(): void
    {
        $request    = new Request([], [], [], [], [], [], '{"vendor":"mautic","package":"test-plugin-bundle"}');
        $controller = $this->generateController(true);

        $response = $controller->removePackageAction($request);

        Assert::assertSame('{"success":true}', $response->getContent());
        Assert::assertSame(200, $response->getStatusCode());
    }

    private function generateController(bool $isPackageInstalled): AjaxController
    {
        $composer = $this->createMock(Composer::class);
        $composer->method('install')->willReturn(new ConsoleOutputModel(0, 'OK'));
        $composer->method('remove')->willReturn(new ConsoleOutputModel(0, 'OK'));
        $composer->method('isInstalled')->willReturn($isPackageInstalled);

        $cacheHelper = $this->createMock(CacheHelper::class);
        $cacheHelper->method('clearSymfonyCache')->willReturn(0);

        $controller = new AjaxController(
            $composer,
            $cacheHelper
        );
        $controller->setContainer(self::$container);

        return $controller;
    }
}
