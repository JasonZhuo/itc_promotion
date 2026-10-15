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
use Itc\Promotion\Api\PromotionProductRepositoryInterface;
use Itc\Promotion\Controller\Adminhtml\Promotion\Move;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\InputException;
use PHPUnit\Framework\TestCase;

class MoveTest extends TestCase
{
    /**
     * Verifies a whole-number position is saved for the posted SKU.
     *
     * @return void
     */
    public function testMoveSubmitsTheSkuAndPosition(): void
    {
        $management = $this->createMock(PromotionManagementInterface::class);
        $management->expects($this->once())->method('moveProduct')->with('24-MB01', 15);
        $stored = [
            ['sku' => '24-MB04', 'position' => 0],
            ['sku' => '24-MB03', 'position' => 1],
            ['sku' => '24-MB01', 'position' => 15],
        ];

        $payload = [];
        $this->controller($management, ['sku' => '24-MB01', 'position' => '15'], $payload, $stored)->execute();

        $this->assertTrue($payload['success']);
        $this->assertSame('Position updated.', $payload['message']);
        $this->assertSame($stored, $payload['items']);
    }

    /**
     * Verifies a non-numeric position is rejected before the list is written.
     *
     * @return void
     */
    public function testMoveRejectsANonNumericPosition(): void
    {
        $management = $this->createMock(PromotionManagementInterface::class);
        $management->expects($this->never())->method('moveProduct');

        $payload = [];
        $this->controller($management, ['sku' => '24-MB01', 'position' => '1a'], $payload)->execute();

        $this->assertFalse($payload['success']);
        $this->assertSame('Enter a whole-number position.', $payload['message']);
    }

    /**
     * Verifies a missing SKU message is returned without a stack trace.
     *
     * @return void
     */
    public function testMoveReturnsTheServiceMessage(): void
    {
        $management = $this->createMock(PromotionManagementInterface::class);
        $management->expects($this->once())
            ->method('moveProduct')
            ->willThrowException(new InputException(__('SKU "24-MB01" is not in the promotion list.')));

        $payload = [];
        $this->controller($management, ['sku' => '24-MB01', 'position' => '0'], $payload)->execute();

        $this->assertFalse($payload['success']);
        $this->assertSame('SKU "24-MB01" is not in the promotion list.', $payload['message']);
    }

    /**
     * Builds the move action around request parameters.
     *
     * @param PromotionManagementInterface $management
     * @param array<string, string> $params
     * @param array<string, mixed> $payload
     * @param array<int, array{sku: string, position: int}> $items
     * @return Move
     */
    private function controller(
        PromotionManagementInterface $management,
        array $params,
        array &$payload,
        array $items = []
    ): Move {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getParam')->willReturnCallback(
            static function (string $key) use ($params): string {
                return $params[$key] ?? '';
            }
        );
        $json = $this->createMock(Json::class);
        $json->method('setData')->willReturnCallback(
            static function (array $data) use (&$payload, $json): Json {
                $payload = $data;
                return $json;
            }
        );
        $jsonFactory = $this->createMock(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($json);
        $storedItems = $this->createMock(PromotionProductRepositoryInterface::class);
        $storedItems->method('getItems')->willReturn($items);
        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);

        return new Move($context, $management, $storedItems, $jsonFactory);
    }
}
