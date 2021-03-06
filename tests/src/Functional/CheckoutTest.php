<?php

namespace Drupal\Tests\commerce_authnet\Functional;

use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_store\StoreCreationTrait;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Tests\commerce\Functional\CommerceBrowserTestBase;

/**
 * Tests the Authorize.net payment configurationf orm.
 *
 * @group commerce_authnet
 */
class CheckoutTest extends CommerceBrowserTestBase {

  use StoreCreationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The product.
   *
   * @var \Drupal\commerce_product\Entity\ProductInterface
   */
  protected $product;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['system', 'field', 'user', 'text',
    'entity', 'views', 'address', 'profile', 'commerce', 'inline_entity_form',
    'commerce_price', 'commerce_store', 'commerce_product', 'commerce_cart',
    'commerce_payment', 'commerce_checkout', 'commerce_order', 'views_ui',
    'commerce_authnet',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $store = $this->createStore('Demo', 'demo@example.com', 'default', TRUE);

    $variation = $this->createEntity('commerce_product_variation', [
      'type' => 'default',
      'sku' => strtolower($this->randomMachineName()),
      'price' => [
        'number' => '9.99',
        'currency_code' => 'USD',
      ],
    ]);

    /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
    $this->product = $this->createEntity('commerce_product', [
      'type' => 'default',
      'title' => 'My product',
      'variations' => [$variation],
      'stores' => [$store],
    ]);

    /** @var \Drupal\commerce_payment\Entity\PaymentGateway $gateway */
    $gateway = PaymentGateway::create([
      'id' => 'authorize_net_us',
      'label' => 'Authorize.net US',
      'plugin' => 'authorizenet',
    ]);
    $gateway->getPlugin()->setConfiguration([
      'api_login' => '5KP3u95bQpv',
      'transaction_key' => '346HZ32z3fP4hTG2',
      'mode' => 'test',
      'payment_method_types' => ['credit_card'],
    ]);
    $gateway->save();

    // Cheat so we don't need JS to interact w/ Address field widget.
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $customer_form_display */
    $customer_form_display = EntityFormDisplay::load('profile.customer.default');
    $address_component = $customer_form_display->getComponent('address');
    $address_component['settings']['default_country'] = 'US';
    $customer_form_display->setComponent('address', $address_component);
    $customer_form_display->save();
  }

  /**
   * Tests than an order can go through checkout steps.
   *
   * @group guest
   */
  public function testGuestAuthorizeNetPayment() {
    $this->drupalLogout();
    $this->drupalGet($this->product->toUrl()->toString());
    $this->submitForm([], 'Add to cart');
    $cart_link = $this->getSession()->getPage()->findLink('your cart');
    $cart_link->click();
    $this->submitForm([], 'Checkout');
    $this->assertSession()->pageTextNotContains('Order Summary');
    $this->submitForm([], 'Continue as Guest');
    $this->submitForm([
      'contact_information[email]' => 'guest@example.com',
      'contact_information[email_confirm]' => 'guest@example.com',
      'payment_information[add_payment_method][payment_details][number]' => '411111111111111',
      'payment_information[add_payment_method][payment_details][expiration][month]' => '02',
      'payment_information[add_payment_method][payment_details][expiration][year]' => '2020',
      'payment_information[add_payment_method][payment_details][security_code]' => '123',
      'payment_information[add_payment_method][billing_information][address][0][given_name]' => 'Johnny',
      'payment_information[add_payment_method][billing_information][address][0][family_name]' => 'Appleseed',
      'payment_information[add_payment_method][billing_information][address][0][address_line1]' => '123 New York Drive',
      'payment_information[add_payment_method][billing_information][address][0][locality]' => 'New York City',
      'payment_information[add_payment_method][billing_information][address][0][administrative_area]' => 'NY',
      'payment_information[add_payment_method][billing_information][address][0][postal_code]' => '10001',
    ], 'Continue to review');
    $this->assertSession()->pageTextContains('You have entered an invalid credit card number.');
    $this->getSession()->getPage()->fillField('payment_information[add_payment_method][payment_details][number]', '4111111111111111');
    $this->submitForm([], 'Continue to review');

    $this->assertSession()->pageTextContains('Contact information');
    $this->assertSession()->pageTextContains('guest@example.com');
    $this->assertSession()->pageTextContains('Payment information');
    $this->assertSession()->pageTextContains('Visa ending in 1111');
    $this->assertSession()->pageTextContains('Expires 2/2020');
    $this->assertSession()->pageTextContains('Order Summary');
    $this->submitForm([], 'Pay and complete purchase');
    $this->assertSession()->pageTextContains('Your order number is 1. You can view your order on your account page when logged in.');
  }

  /**
   * Tests than an order can go through checkout steps.
   *
   * @group registered
   */
  public function testRegisteredAuthorizeNetPayment() {
    $this->drupalGet($this->product->toUrl()->toString());
    $this->submitForm([], 'Add to cart');
    $cart_link = $this->getSession()->getPage()->findLink('your cart');
    $cart_link->click();
    $this->submitForm([], 'Checkout');
    $this->assertSession()->pageTextContains('Order Summary');
    $this->submitForm([
      'payment_information[add_payment_method][payment_details][number]' => '4111111111111111',
      'payment_information[add_payment_method][payment_details][expiration][month]' => '02',
      'payment_information[add_payment_method][payment_details][expiration][year]' => '2020',
      'payment_information[add_payment_method][payment_details][security_code]' => '123',
      'payment_information[add_payment_method][billing_information][address][0][given_name]' => 'Johnny',
      'payment_information[add_payment_method][billing_information][address][0][family_name]' => 'Appleseed',
      'payment_information[add_payment_method][billing_information][address][0][address_line1]' => '123 New York Drive',
      'payment_information[add_payment_method][billing_information][address][0][locality]' => 'New York City',
      'payment_information[add_payment_method][billing_information][address][0][administrative_area]' => 'NY',
      'payment_information[add_payment_method][billing_information][address][0][postal_code]' => '10001',
    ], 'Continue to review');
    $this->assertSession()->pageTextContains('Contact information');
    $this->assertSession()->pageTextContains($this->loggedInUser->getEmail());
    $this->assertSession()->pageTextContains('Payment information');
    $this->assertSession()->pageTextContains('Visa ending in 1111');
    $this->assertSession()->pageTextContains('Expires 2/2020');
    $this->assertSession()->pageTextContains('Order Summary');
    $this->submitForm([], 'Pay and complete purchase');
    $this->assertSession()->pageTextContains('Your order number is 1. You can view your order on your account page when logged in.');
  }

}
