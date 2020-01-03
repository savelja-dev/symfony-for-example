<?php

namespace App\EventListener;


class PaymentSubscriber implements EventSubscriber
{
    private $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getSubscribedEvents()
    {
        return [
            Events::postUpdate,
        ];
    }

    /**
     * Update your balance after payment and successful payment notification.
     * Then orders relating to this invoice change the status to paid.
     * @param LifecycleEventArgs $args
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function postUpdate(LifecycleEventArgs $args)
    {
        $object = $args->getObject();
        if ($object instanceof Payment && ($paidInvoice = $object->getPaidInvoices()->first())) {
            /* @var $paidInvoice Invoice */
            $entityManager = $args->getEntityManager();
            $userInst = $object->getUser();
            $data = $paidInvoice->getData();
            if ($object->getType()->getCategory() === PaymentTypeVocabularyUtil::CATEGORY_ID_BLOCK_CHAIN) {
                $chargeAmount = PaymentUtil::calculateChargeAmount($data['exchange_rate'], $data['response']['inTransaction']);
                $userInst->addBalance($chargeAmount);
                $event = new SendEmailEvent(
                    EmailUtil::TEMPLATE_ID_CHARGE_BALANCE,
                    [
                        'user' => $userInst,
                        'chargeAmount' => $chargeAmount,
                    ]
                );
                $this->eventDispatcher->dispatch($event, $event::NAME);
            } elseif ($object->getType()->getCategory() === PaymentTypeVocabularyUtil::CATEGORY_ID_TRANSFER) {
                $chargeAmount = (int)$data['charge']['amount'];
                $userInst->addBalance($chargeAmount);
                $event = new SendEmailEvent(
                    EmailUtil::TEMPLATE_ID_CHARGE_BALANCE,
                    [
                        'user' => $userInst,
                        'chargeAmount' => $chargeAmount,
                    ]
                );
                $this->eventDispatcher->dispatch($event, $event::NAME);
            }
            $this->processOrders($object, $userInst);
            $this->processPaymentExceedInvoices($object);
            $entityManager->flush();
        }
    }

    /**
     * If the user has enough funds on the balance then the orders related to the paid payment and
     * there is a status of the pending are transferred to the status paid.
     * @param Payment $payment
     * @param User $user
     */
    private function processOrders(Payment $payment, User $user): void
    {
        /* @var $orders Collection|Order[] */
        $orders = $payment->getOrders()->filter(function (Order $order) {
            return OrderUtil::STATUS_PENDING === $order->getStatus();
        });
        $ordersPrice = OrderUtil::getOrdersPrice($orders);
        if (0 !== $ordersPrice && $user->getBalance() < $ordersPrice) {
            return;
        }
        foreach ($orders as $order) {
            $order->setStatus(OrderUtil::STATUS_PAID);
            $this->chargeBalance($user, $payment, $order);
        }
    }

    /**
     * It sets the entity of the user's balance order for showing in the income and expense section
     * @param User $user
     * @param Payment $payment
     * @param Order $order
     */
    private function chargeBalance(User $user, Payment $payment, Order $order): void
    {
        $orderType = $order->getType();
        if (null === $orderType->getServiceType()) {
            $balance = $user->getBalance();
            if (PaymentTypeVocabularyUtil::CATEGORY_ID_BALANCE !== $payment->getType()->getCategory()) {
                // If we do not charging balance from custom balance.
                $balance += $orderType->getPrice();
            }
            $order->setBalance($balance);
            return;
        }
    }

    /**
     * Changes of status to all invoices relating to paid payment but have not yet been paid
     * @param Payment $payment
     */
    private function processPaymentExceedInvoices(Payment $payment): void
    {
        foreach ($payment->getPendingInvoices() as $invoice) {
            $invoice->setStatus(InvoiceUtil::STATUS_CLOSED);
        }
    }
}