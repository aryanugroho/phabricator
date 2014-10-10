<?php

final class FundInitiativeEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorFundApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Fund Initiatives');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = FundInitiativeTransaction::TYPE_NAME;
    $types[] = FundInitiativeTransaction::TYPE_DESCRIPTION;
    $types[] = FundInitiativeTransaction::TYPE_RISKS;
    $types[] = FundInitiativeTransaction::TYPE_STATUS;
    $types[] = FundInitiativeTransaction::TYPE_BACKER;
    $types[] = FundInitiativeTransaction::TYPE_MERCHANT;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case FundInitiativeTransaction::TYPE_NAME:
        return $object->getName();
      case FundInitiativeTransaction::TYPE_DESCRIPTION:
        return $object->getDescription();
      case FundInitiativeTransaction::TYPE_RISKS:
        return $object->getRisks();
      case FundInitiativeTransaction::TYPE_STATUS:
        return $object->getStatus();
      case FundInitiativeTransaction::TYPE_BACKER:
        return null;
      case FundInitiativeTransaction::TYPE_MERCHANT:
        return $object->getMerchantPHID();
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case FundInitiativeTransaction::TYPE_NAME:
      case FundInitiativeTransaction::TYPE_DESCRIPTION:
      case FundInitiativeTransaction::TYPE_RISKS:
      case FundInitiativeTransaction::TYPE_STATUS:
      case FundInitiativeTransaction::TYPE_BACKER:
      case FundInitiativeTransaction::TYPE_MERCHANT:
        return $xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case FundInitiativeTransaction::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        return;
      case FundInitiativeTransaction::TYPE_DESCRIPTION:
        $object->setDescription($xaction->getNewValue());
        return;
      case FundInitiativeTransaction::TYPE_RISKS:
        $object->setRisks($xaction->getNewValue());
        return;
      case FundInitiativeTransaction::TYPE_MERCHANT:
        $object->setMerchantPHID($xaction->getNewValue());
        return;
      case FundInitiativeTransaction::TYPE_STATUS:
        $object->setStatus($xaction->getNewValue());
        return;
      case FundInitiativeTransaction::TYPE_BACKER:
        $backer = id(new FundBackerQuery())
          ->setViewer($this->requireActor())
          ->withPHIDs(array($xaction->getNewValue()))
          ->executeOne();
        if (!$backer) {
          throw new Exception(pht('No such backer!'));
        }

        $backer_amount = $backer->getAmountAsCurrency();
        $total = $object->getTotalAsCurrency()->add($backer_amount);
        $object->setTotalAsCurrency($total);

        return;
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
      case PhabricatorTransactions::TYPE_EDGE:
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case FundInitiativeTransaction::TYPE_NAME:
      case FundInitiativeTransaction::TYPE_DESCRIPTION:
      case FundInitiativeTransaction::TYPE_RISKS:
      case FundInitiativeTransaction::TYPE_STATUS:
      case FundInitiativeTransaction::TYPE_MERCHANT:
      case FundInitiativeTransaction::TYPE_BACKER:
        // TODO: Maybe we should apply the backer transaction from here?
        return;
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
      case PhabricatorTransactions::TYPE_EDGE:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);

    switch ($type) {
      case FundInitiativeTransaction::TYPE_NAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getName(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Initiative name is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
        break;
      case FundInitiativeTransaction::TYPE_MERCHANT:
        $missing = $this->validateIsEmptyTextField(
          $object->getName(),
          $xactions);
        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Payable merchant is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        } else if ($xactions) {
          $merchant_phid = last($xactions)->getNewValue();

          // Make sure the actor has permission to edit the merchant they're
          // selecting. You aren't allowed to send payments to an account you
          // do not control.
          $merchants = id(new PhortuneMerchantQuery())
            ->setViewer($this->requireActor())
            ->withPHIDs(array($merchant_phid))
            ->requireCapabilities(
              array(
                PhabricatorPolicyCapability::CAN_VIEW,
                PhabricatorPolicyCapability::CAN_EDIT,
              ))
            ->execute();
          if (!$merchants) {
            $error = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht(
                'You must specify a merchant account you control as the '.
                'recipient of funds from this initiative.'),
              last($xactions));
            $errors[] = $error;
          }
        }
        break;
    }

    return $errors;
  }


}