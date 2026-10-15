<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Test\Unit\Controller\Adminhtml\Promotion;

use Itc\Promotion\Api\PromotionManagementInterface;
use Itc\Promotion\Controller\Adminhtml\Promotion\Save;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\TestCase;

class SaveTest extends TestCase
{
    /**
     * Verifies an edited SKU is replaced through the shared promotion service.
     *
     * @return void
     */
    public function testEditSubmitsTheNewSkuToChangeProduct(): void
    {
        $management = $this->createMock(PromotionManagementInterface::class);
        $management->expects($this->once())
            ->method('changeProduct')
            ->with('24-MB01', '24-WB02', 1);
        $management->expects($this->never())->method('addProduct');
        $management->expects($this->never())->method('moveProduct');

        $paths = [];
        $controller = $this->controller($management, [
            'sku' => '24-WB02',
            'original_sku' => '24-MB01',
            'position' => '1',
        ], $paths);
        $controller->execute();

        $this->assertSame(['*/*/index'], array_column($paths, 0));
    }

    /**
     * Verifies an add form posts the SKU at the top level, not inside a data array.
     *
     * @return void
     */
    public function testAddReadsTheSkuFromTheTopLevelPost(): void
    {
        $management = $this->createMock(PromotionManagementInterface::class);
        $management->expects($this->once())
            ->method('addProducts')
            ->with(['24-MB01'], [0]);
        $management->expects($this->never())->method('changeProduct');

        $paths = [];
        $controller = $this->controller($management, [], $paths, [
            'sku' => '24-MB01',
            'position' => '0',
            'form_key' => 'token',
        ]);
        $controller->execute();

        $this->assertSame(['*/*/index'], array_column($paths, 0));
    }

    /**
     * Verifies comma-separated SKUs and positions are added together.
     *
     * @return void
     */
    public function testAddSplitsCommaSeparatedSkusAndPositions(): void
    {
        $management = $this->createMock(PromotionManagementInterface::class);
        $management->expects($this->once())
            ->method('addProducts')
            ->with(['24-MB01', '24-MB04', '24-MB03'], [0, 1, 2]);

        $paths = [];
        $controller = $this->controller($management, [], $paths, [
            'sku' => '24-MB01, 24-MB04, 24-MB03',
            'position' => '0, 1, 2',
        ]);
        $controller->execute();

        $this->assertSame(['*/*/index'], array_column($paths, 0));
    }

    /**
     * Verifies a failed SKU change does not redirect as a successful save.
     *
     * @return void
     */
    public function testFailedSkuChangeReturnsToTheEditForm(): void
    {
        $management = $this->createMock(PromotionManagementInterface::class);
        $management->expects($this->once())
            ->method('changeProduct')
            ->willThrowException(new InputException(__('These SKUs do not exist: NOPE')));
        $management->expects($this->never())->method('addProduct');

        $paths = [];
        $controller = $this->controller($management, [
            'sku' => 'NOPE',
            'original_sku' => '24-MB01',
            'position' => '0',
        ], $paths);
        $controller->execute();

        $this->assertSame([['*/*/edit', ['sku' => '24-MB01']]], $paths);
    }

    /**
     * Builds the admin save action around a request payload.
     *
     * @param PromotionManagementInterface $management
     * @param array<string, string> $data
     * @param array<int, array{0: string, 1: array<string, string>}> $paths
     * @param array<string, string> $post
     * @return Save
     */
    private function controller(
        PromotionManagementInterface $management,
        array $data,
        array &$paths,
        array $post = []
    ): Save {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getParam')->willReturnCallback(
            static function (string $key, mixed $default = null) use ($data): mixed {
                return $key === 'data' ? $data : $default;
            }
        );
        $request->method('getPostValue')->willReturn($post);
        $redirect = $this->createMock(Redirect::class);
        $redirect->method('setPath')->willReturnCallback(
            static function (string $path, array $params = []) use (&$paths, $redirect): Redirect {
                $paths[] = [$path, $params];
                return $redirect;
            }
        );
        $redirectFactory = $this->createMock(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($redirect);
        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getMessageManager')->willReturn($this->createMock(ManagerInterface::class));
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);

        return new Save($context, $management);
    }
}
