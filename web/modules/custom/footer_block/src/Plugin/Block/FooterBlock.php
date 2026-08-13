<?php

namespace Drupal\footer_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\footer_block\Form\TaggedOctopusSubscribeForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Footer' Block.
 *
 * @Block(
 *   id = "footer_block",
 *   admin_label = @Translation("Footer Block"),
 *   category = @Translation("Custom")
 * )
 */
class FooterBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The EmailOctopus audience ID.
   */
  private const EMAIL_OCTOPUS_LIST_ID = '9892fd12-9618-11f1-b011-7369a6de8e41';

  /**
   * Constructs a FooterBlock instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected FormBuilderInterface $formBuilder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'footer_block',
      '#newsletter_form' => $this->formBuilder->getForm(
        TaggedOctopusSubscribeForm::class,
        self::EMAIL_OCTOPUS_LIST_ID,
        $this->t('Iratkozz fel hírlevelünkre!'),
        $this->t('Értesülj elsőként a helyi termelőkről, újdonságokról és ajánlatokról.'),
        $this->t('Köszönjük a feliratkozást!'),
      ),
    ];
  }

}
