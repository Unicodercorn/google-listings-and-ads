<?php
declare( strict_types=1 );

namespace Automattic\WooCommerce\GoogleListingsAndAds\Tests\Unit\API\Google;

use Automattic\WooCommerce\GoogleListingsAndAds\API\Google\AdsAsset;
use Automattic\WooCommerce\GoogleListingsAndAds\Options\OptionsInterface;
use Automattic\WooCommerce\GoogleListingsAndAds\Tests\Framework\UnitTest;
use Automattic\WooCommerce\GoogleListingsAndAds\Tests\Tools\HelperTrait\GoogleAdsClientTrait;
use PHPUnit\Framework\MockObject\MockObject;
use Automattic\WooCommerce\GoogleListingsAndAds\API\Google\AssetFieldType;
use Automattic\WooCommerce\GoogleListingsAndAds\API\Google\CallToActionType;
use Google\Ads\GoogleAds\Util\V11\ResourceNames;
use Automattic\WooCommerce\GoogleListingsAndAds\Proxies\WP;
use Exception;
use WP_Error;


defined( 'ABSPATH' ) || exit;

/**
 * Class AdsAssetTest
 *
 * @package Automattic\WooCommerce\GoogleListingsAndAds\Tests\Unit\API\Google
 *
 * @property MockObject|OptionsInterface $options
 * @property AdsAsset                    $asset
 * @property MockObject|WP               $wp
 */
class AdsAssetTest extends UnitTest {

	use GoogleAdsClientTrait;

	protected const TEMPORARY_ID  = -6;
	protected const TEST_ASSET_ID = 6677889911;

	/**
	 * Runs before each test is executed.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->ads_client_setup();

		$this->options = $this->createMock( OptionsInterface::class );
		$this->wp      = $this->createMock( WP::class );
		$this->options->method( 'get_ads_id' )->willReturn( $this->ads_id );

		$this->asset = new AdsAsset( $this->wp );
		$this->asset->set_options_object( $this->options );
	}

	protected function assertAssetTypeConversion( $data ) {
		$row      = $this->generate_asset_row_mock( $data );
		$expected = [
			'id'      => self::TEST_ASSET_ID,
			'content' => $data['content'],
		];
		$this->assertEquals( $expected, $this->asset->convert_asset( $row ) );
	}

	protected function assertHasAssetCreateOperation( $asset_operation, $asset_id = self::TEMPORARY_ID ) {
		$this->assertTrue( $asset_operation->hasCreate() );
		$this->assertEquals( ResourceNames::forAsset( $this->options->get_ads_id(), $asset_id ), $asset_operation->getCreate()->getResourceName() );
	}

	public function test_convert_asset_text() {
		$data = [
			'field_type' => AssetFieldType::HEADLINE,
			'content'    => 'Test headline',
			'id'         => self::TEST_ASSET_ID,
		];
		$this->assertAssetTypeConversion( $data );
	}

	public function test_convert_asset_image() {
		$data = [
			'field_type' => AssetFieldType::SQUARE_MARKETING_IMAGE,
			'content'    => 'https://example.com/image.jpg',
			'id'         => self::TEST_ASSET_ID,
		];

		$this->assertAssetTypeConversion( $data );
	}

	public function test_convert_asset_call_action() {
		$data = [
			'field_type' => AssetFieldType::CALL_TO_ACTION_SELECTION,
			'content'    => CallToActionType::SHOP_NOW,
			'id'         => self::TEST_ASSET_ID,
		];

		$this->assertAssetTypeConversion( $data );
	}

	public function test_create_operation_text_asset() {
		$data = [
			'field_type' => AssetFieldType::HEADLINE,
			'content'    => 'Test headline',
		];

		$operation       = $this->asset->create_operation( $data, self::TEMPORARY_ID );
		$asset_operation = $operation->getAssetOperation();

		$this->assertHasAssetCreateOperation( $asset_operation );
		$this->assertEquals( $data['content'], $asset_operation->getCreate()->getTextAsset()->getText() );

	}

	public function test_create_operation_call_to_action_asset() {
		$data = [
			'field_type' => AssetFieldType::CALL_TO_ACTION_SELECTION,
			'content'    => CallToActionType::SHOP_NOW,
		];

		$operation       = $this->asset->create_operation( $data, self::TEMPORARY_ID );
		$asset_operation = $operation->getAssetOperation();

		$this->assertHasAssetCreateOperation( $asset_operation );
		$this->assertEquals( CallToActionType::number( $data['content'] ), $asset_operation->getCreate()->getCallToActionAsset()->getCallToAction() );

	}

	public function test_create_operation_image_asset() {
		$data = [
			'field_type' => AssetFieldType::SQUARE_MARKETING_IMAGE,
			'content'    => 'https://example.com/image.jpg',
		];

		$this->wp->expects( $this->exactly( 1 ) )
			->method( 'wp_remote_get' )
			->with( $data['content'] )
			->willReturn(
				[
					'body' => $data['content'],
				]
			);

		$operation       = $this->asset->create_operation( $data, self::TEMPORARY_ID );
		$asset_operation = $operation->getAssetOperation();

		$this->assertHasAssetCreateOperation( $asset_operation );
		$this->assertEquals( $data['content'], $asset_operation->getCreate()->getImageAsset()->getData() );

	}

	public function test_create_operation_image_asset_exception() {
		$data = [
			'field_type' => AssetFieldType::SQUARE_MARKETING_IMAGE,
			'content'    => 'https://incorrect_url.com/image.jpg',
		];

		$this->wp->expects( $this->exactly( 1 ) )
			->method( 'wp_remote_get' )
			->with( $data['content'] )
			->willReturn(
				new WP_Error( 'Incorrect image asset url.' )
			);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Incorrect image asset url.' );
		$this->asset->create_operation( $data, self::TEMPORARY_ID );
	}

	public function test_create_operation_invalid_asset_field_type() {
		$data = [
			'field_type' => 'invalid',
			'content'    => CallToActionType::SHOP_NOW,
		];

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Asset Field type not supported' );

		$this->asset->create_operation( $data, self::TEMPORARY_ID );

	}


}
