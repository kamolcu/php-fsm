<?php
use Finite\Event\FiniteEvents;
use Finite\StatefulInterface;
use Finite\StateMachine\StateMachine;
use Finite\State\State;
use Finite\State\StateInterface;
use Finite\Transition\Transition;
use Carbon\Carbon;

class OrderFsm implements StatefulInterface
{
    private $machine;
    private $state;
    public $orderDateTime;
    public $orderPayDateTime;
    public function __construct($initial_state = null) {
        $this->orderDateTime = Carbon::now();
        $this->machine = new StateMachine();

        $orderGuard = App::make('OrderFsmGuard', array(
            $this
        ));
        // http://php.net/manual/en/language.types.callable.php
        // Type 3: Object method call
        // $obj = new MyClass();
        // call_user_func(array($obj, 'myCallbackMethod'));
        $callbacks = array(
            $orderGuard,
            'canRefund'
        );
        // Define states
        // __construct($name, $type = self::TYPE_NORMAL, array $transitions = array(), array $properties = array())
        $this->machine->addState(new State('PlacedOrder', StateInterface::TYPE_INITIAL, array() , array(
            'payable' => true,
        )));
        $this->machine->addState('Incompleted');
        $this->machine->addState(new State('Paid', StateInterface::TYPE_NORMAL, array() , array(
            'refundable' => true,
        )));
        $this->machine->addState('Shipped');
        $this->machine->addState(new State('Received', StateInterface::TYPE_NORMAL, array() , array(
            'transferable' => true,
        )));
        $this->machine->addState(new State('Transferred', StateInterface::TYPE_FINAL));
        $this->machine->addState('RefundRequested');
        $this->machine->addState('RefundAccepted');
        $this->machine->addState('RefundDeclined');
        $this->machine->addState('Continue');
        $this->machine->addState('Refund');
        $this->machine->addState(new State('BuyerAskWLS', StateInterface::TYPE_FINAL));
        $this->machine->addState(new State('SellerAskWLS', StateInterface::TYPE_FINAL));
        // Define transitions
        // __construct($name, $initialStates, $state, $guard = null)
        $this->machine->addTransition(new Transition('BuyerPayFail', 'PlacedOrder', 'Incompleted', null));
        $this->machine->addTransition(new Transition('BuyerPaySuccess', 'PlacedOrder', 'Paid', null));
        $this->machine->addTransition(new Transition('SellerClickShip', 'Paid', 'Shipped', null));
        $this->machine->addTransition(new Transition('WeloveClickTransfer', 'Received', 'Transferred', null));
        $this->machine->addTransition(new Transition('BuyerClickRefund', 'Paid', 'RefundRequested', $callbacks));
        $this->machine->addTransition(new Transition('BuyerClickRefund', 'Shipped', 'RefundRequested', $callbacks));

        $this->machine->setObject($this);
        $this->registerEvents();
        if (is_string($initial_state)) {
            $this->setFiniteState($initial_state);
        }
        $this->machine->initialize();
    }

    public function setFiniteState($state) {
        $this->state = $state;
    }

    public function getFiniteState() {
        return $this->state;
    }
    public function getStateMachine() {
        return $this->machine;
    }

    public function registerEvents() {
        $this->machine->getDispatcher()->addListener(FiniteEvents::PRE_TRANSITION, function ($event) {
            $state = $event->getTransition()->getState();
            Session::put('PRE_TRANSITION', $state);
        });

        $transitionName = 'BuyerPaySuccess';
        $this->machine->getDispatcher()->addListener(FiniteEvents::PRE_TRANSITION . '.' . $transitionName, function ($event) use ($transitionName) {
            $state = $event->getTransition()->getState();
            Session::put('PRE_TRANSITION-' . $transitionName, $state);
        });

        $this->machine->getDispatcher()->addListener(FiniteEvents::POST_TRANSITION, function ($event) {
            $state = $event->getTransition()->getState();
            Session::put('POST_TRANSITION', $state);
        });

        $this->machine->getDispatcher()->addListener(FiniteEvents::POST_TRANSITION . '.' . $transitionName, function ($event) use ($transitionName) {
            $state = $event->getTransition()->getState();
            Session::put('POST_TRANSITION-' . $transitionName, $state);
        });
    }
}
