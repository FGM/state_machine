<?php

/**
 * @file
 * Contains \Drupal\Tests\state_machine\Unit\WorkflowTest.
 */

namespace Drupal\Tests\state_machine\Unit;

use Drupal\Core\Entity\EntityInterface;
use Drupal\state_machine\Guard\GuardFactoryInterface;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\Workflow;
use Drupal\state_machine\Plugin\Workflow\WorkflowState;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\state_machine\Plugin\Workflow\Workflow
 * @group state_machine
 */
class WorkflowTest extends UnitTestCase {

  /**
   * @covers ::getLabel
   */
  public function testGetLabel() {
    $guard_factory = $this->prophesize(GuardFactoryInterface::class);
    $plugin_definition = [
      'states' => [],
      'transitions' => [],
      'label' => 'test label',
    ];
    $workflow = new Workflow([], 'test', $plugin_definition, $guard_factory->reveal());

    $this->assertEquals('test label', $workflow->getLabel());
  }

  /**
   * @covers ::getStates
   * @covers ::getState
   */
  public function testGetStates() {
    $guard_factory = $this->prophesize(GuardFactoryInterface::class);
    $plugin_definition = [
      'states' => [
        'draft' => [
          'label' => 'Draft',
        ],
      ],
      'transitions' => [],
    ];
    $workflow = new Workflow([], 'test', $plugin_definition, $guard_factory->reveal());

    $states = $workflow->getStates();
    $state = $workflow->getState('draft');
    $this->assertEquals($state, $states['draft']);
    $this->assertEquals('draft', $state->getId());
    $this->assertEquals('Draft', $state->getLabel());
    $this->assertEquals(['draft' => $state], $workflow->getStates());
  }

  /**
   * @covers ::getTransitions
   * @covers ::getTransition
   */
  public function testGetTransitions() {
    $guard_factory = $this->prophesize(GuardFactoryInterface::class);
    $plugin_definition = [
      'states' => [
        'draft' => [
          'label' => 'Draft',
        ],
        'published' => [
          'label' => 'Published',
        ],
      ],
      'transitions' => [
        'publish' => [
          'label' => 'Publish',
          'from' => ['draft'],
          'to' => 'published',
        ],
      ],
    ];
    $workflow = new Workflow([], 'test', $plugin_definition, $guard_factory->reveal());

    $transition = $workflow->getTransition('publish_draft');
    $this->assertEquals($transition, $states['publish_draft']);
    $this->assertEquals('publish', $transition->getId());
    $this->assertEquals('Publish', $transition->getLabel());
    $this->assertEquals(['draft' => $workflow->getState('draft')], $transition->getFromStates());
    $this->assertEquals($workflow->getState('published'), $transition->getToState());
    $this->assertEquals(['publish' => $transition], $workflow->getTransitions());
  }

  /**
   * @covers ::getPossibleTransitions
   */
  public function testGetPossibleTransitions() {
    $guard_factory = $this->prophesize(GuardFactoryInterface::class);
    $plugin_definition = [
      'states' => [
        'draft' => [
          'label' => 'Draft',
        ],
        'review' => [
          'label' => 'Review',
        ],
        'published' => [
          'label' => 'Published',
        ],
      ],
      'transitions' => [
        'send_to_review' => [
          'label' => 'Send to review',
          'from' => ['draft'],
          'to' => 'review',
        ],
        'publish' => [
          'label' => 'Publish',
          'from' => ['review'],
          'to' => 'published',
        ],
      ],
    ];
    $workflow = new Workflow([], 'test', $plugin_definition, $guard_factory->reveal());

    $transition = $workflow->getTransition('send_to_review');
    $this->assertEquals(['send_to_review' => $transition], $workflow->getPossibleTransitions('draft'));
    $transition = $workflow->getTransition('publish');
    $this->assertEquals(['publish' => $transition], $workflow->getPossibleTransitions('review'));
    $this->assertEquals([], $workflow->getPossibleTransitions('published'));
    // Passing an empty state should return all transitions.
    $this->assertEquals($workflow->getTransitions(), $workflow->getPossibleTransitions());
  }

  /**
   * @covers ::getAllowedTransitions
   */
  public function testGetAllowedTransitions() {
    $plugin_definition = [
      'states' => [
        'draft' => [
          'label' => 'Draft',
        ],
        'review' => [
          'label' => 'Review',
        ],
        'published' => [
          'label' => 'Published',
        ],
      ],
      'transitions' => [
        'send_to_review' => [
          'label' => 'Send to review',
          'from' => ['draft'],
          'to' => 'review',
        ],
        'publish' => [
          'label' => 'Publish',
          'from' => ['review'],
          'to' => 'published',
        ],
      ],
      'group' => 'default',
    ];
    $guard_allow = $this->prophesize(GuardInterface::class);
    $guard_allow
      ->allowed(Argument::cetera())
      ->willReturn(TRUE);
    $guard_deny_publish = $this->prophesize(GuardInterface::class);
    $guard_deny_publish
      ->allowed($transitions['publish'], Argument::any(), Argument::any())
      ->willReturn(FALSE);
    $guard_deny_publish
      ->allowed(Argument::any(), Argument::any(), Argument::any())
      ->willReturn(TRUE);
    $guard_factory = $this->prophesize(GuardFactoryInterface::class);
    $guard_factory
      ->get('default')
      ->willReturn([$guard_allow->reveal(), $guard_deny_publish->reveal()]);
    $workflow = new Workflow([], 'test', $plugin_definition, $guard_factory->reveal());

    $entity = $this->prophesize(EntityInterface::class)->reveal();
    $transition = $this->getTransition('send_to_review');
    $this->assertEquals(['send_to_review' => $transition], $workflow->getAllowedTransitions('draft', $entity));
    $this->assertEquals([], $workflow->getAllowedTransitions('review', $entity));
  }

}
