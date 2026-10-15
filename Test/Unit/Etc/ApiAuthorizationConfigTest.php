<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Test\Unit\Etc;

use PHPUnit\Framework\TestCase;

class ApiAuthorizationConfigTest extends TestCase
{
    /**
     * Verifies get Is Anonymous And Put Requires The Update Acl.
     *
     * @return void
     */
    public function testGetIsAnonymousAndPutRequiresTheUpdateAcl(): void
    {
        $xml = simplexml_load_file(dirname(__DIR__, 3) . '/etc/webapi.xml');
        $this->assertNotFalse($xml);
        $routes = [];
        foreach ($xml->route as $route) {
            $routes[(string) $route['method']] = (string) $route->resources->resource['ref'];
        }
        $this->assertSame('anonymous', $routes['GET']);
        $this->assertSame('Itc_Promotion::update', $routes['PUT']);
        $this->assertArrayNotHasKey('anonymous', array_flip([$routes['PUT']]));
    }

    /**
     * Verifies update Acl Is Declared.
     *
     * @return void
     */
    public function testUpdateAclIsDeclared(): void
    {
        $xml = simplexml_load_file(dirname(__DIR__, 3) . '/etc/acl.xml');
        $this->assertNotFalse($xml);
        $ids = $xml->xpath('//resource[@id="Itc_Promotion::update"]');
        $this->assertNotEmpty($ids);
    }

    /**
     * Verifies the admin grid collection is registered in the adminhtml area.
     *
     * @return void
     */
    public function testListingCollectionIsRegisteredForAdminhtml(): void
    {
        $xml = simplexml_load_file(dirname(__DIR__, 3) . '/etc/adminhtml/di.xml');
        $this->assertNotFalse($xml);
        $items = $xml->xpath('//item[@name="itc_promotion_listing_data_source"]');
        $this->assertNotEmpty($items);
        $this->assertSame(
            'Itc\\Promotion\\Model\\ResourceModel\\PromotionItem\\Grid\\Collection',
            (string) $items[0]
        );
    }
}
