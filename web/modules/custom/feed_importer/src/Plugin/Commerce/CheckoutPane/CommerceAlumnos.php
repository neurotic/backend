<?php
namespace Drupal\feed_importer\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Provides the coupons pane.
 *
 * @CommerceCheckoutPane(
 *   id = "alumnos",
 *   label = @Translation("Alumnos del curso"),
 *   default_step = "order_information",
 * )
 */
class CommerceAlumnos extends CheckoutPaneBase implements CheckoutPaneInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $checkout_flow, $entity_type_manager);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
        'single_coupon' => FALSE,
      ] + parent::defaultConfiguration();
  }


  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary() {
    $summary = !empty($this->configuration['single_coupon']) ? $this->t('Single Coupon Usage on Order: Yes') : $this->t('Single Coupon Usage on Order: No');
    return $summary;
  }


  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['single_coupon'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Single Coupon Usage on Order?'),
      '#description' => $this->t('User can enter only one coupon on order.'),
      '#default_value' => $this->configuration['single_coupon'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['single_coupon'] = !empty($values['single_coupon']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $paragraph = Paragraph::create([
      'type' => 'alumno', // Paragraph type.
    ]);

    $pane_form['alumnos'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Coupon'),
      '#default_value' => '',
      '#required' => FALSE,
    ];
    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    if(!empty($values['coupon'])){
      $coupon_code = $values['coupon'];
      /* @var \Drupal\commerce_promotion\Entity\Coupon $commerce_coupon */
      $commerce_coupon = $this->entityTypeManager->getStorage('commerce_promotion_coupon')->loadByCode($coupon_code);
      $valid = false;
      if($commerce_coupon){
        $promotion = $commerce_coupon->getPromotion();
        $valid = $promotion->applies($this->order) ? true : false;
        if($valid){
          foreach($this->order->coupons->referencedEntities() as $coupon){
            if($commerce_coupon->id() == $coupon->id()){
              $form_state->setError($pane_form, $this->t('Coupon already applied to order.'));
            }
          }
        }
      }
      if(!$valid){
        $form_state->setError($pane_form, $this->t('The specified coupon does not apply for this order.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    if(!empty($values['coupon'])){
      $coupon_code = $values['coupon'];
      $commerce_coupon = $this->entityTypeManager->getStorage('commerce_promotion_coupon')->loadByCode($coupon_code);
      if($commerce_coupon){
        $this->order->coupons[] = $commerce_coupon->id();
        $coupon_order_processor = \Drupal::service('commerce_promotion.promotion_order_processor');
        $coupon_order_processor->process($this->order);
        $this->order->save();
      }
    }
  }
}