<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\GlobalSales\Lib\Service\Calc;

use Praxigento\BonusBase\Service\Period\Request\GetForDependentCalc as PeriodGetForDependentCalcRequest;
use Praxigento\BonusGlobalSales\Config as Cfg;
use Praxigento\Wallet\Service\Operation\Request\AddToWalletActive as WalletOperationAddToWalletActiveRequest;

/**
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Call
    extends \Praxigento\Core\App\Service\Base\Call
    implements \Praxigento\Bonus\GlobalSales\Lib\Service\ICalc
{
    /** @var  \Praxigento\BonusBase\Service\IPeriod */
    protected $_callBasePeriod;
    /** @var  \Praxigento\Wallet\Service\IOperation */
    protected $_callWalletOperation;
    /** @var  \Praxigento\Core\Api\App\Repo\Transaction\Manager */
    protected $_manTrans;
    /** @var  \Praxigento\BonusBase\Repo\Dao\Compress */
    protected $_repoBonusCompress;
    /** @var \Praxigento\BonusBase\Repo\Service\IModule */
    protected $_repoBonusService;
    /** @var \Praxigento\BonusBase\Repo\Dao\Type\Calc */
    protected $_repoBonusTypeCalc;
    /** @var \Praxigento\Bonus\GlobalSales\Lib\Repo\IModule */
    protected $_repoMod;
    /** @var  Sub\Bonus */
    protected $_subBonus;
    /** @var Sub\Qualification */
    protected $_subQualification;
    /** @var \Psr\Log\LoggerInterface */
    protected $logger;

    /**
     * Call constructor.
     * @param \Praxigento\Core\Api\App\Logger\Main $logger
     * @param \Magento\Framework\ObjectManagerInterface $manObj
     * @param \Praxigento\Core\Api\App\Repo\Transaction\Manager $manTrans
     * @param \Praxigento\Bonus\GlobalSales\Lib\Repo\IModule $daoMod
     * @param \Praxigento\BonusBase\Repo\Service\IModule $daoBonusService
     * @param \Praxigento\BonusBase\Repo\Dao\Compress $daoBonusCompress
     * @param \Praxigento\BonusBase\Repo\Dao\Type\Calc $daoBonusTypeCalc
     * @param \Praxigento\BonusBase\Service\IPeriod $callBasePeriod
     * @param \Praxigento\Wallet\Service\IOperation $callWalletOperation
     * @param Sub\Bonus $subBonus
     * @param Sub\Qualification $subQual
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\Core\Api\App\Repo\Transaction\Manager $manTrans,
        \Praxigento\Bonus\GlobalSales\Lib\Repo\IModule $daoMod,
        \Praxigento\BonusBase\Repo\Service\IModule $daoBonusService,
        \Praxigento\BonusBase\Repo\Dao\Compress $daoBonusCompress,
        \Praxigento\BonusBase\Repo\Dao\Type\Calc $daoBonusTypeCalc,
        \Praxigento\BonusBase\Service\IPeriod $callBasePeriod,
        \Praxigento\Wallet\Service\IOperation $callWalletOperation,
        Sub\Bonus $subBonus,
        Sub\Qualification $subQual
    ) {
        parent::__construct($logger, $manObj);
        $this->_manTrans = $manTrans;
        $this->_repoMod = $daoMod;
        $this->_repoBonusService = $daoBonusService;
        $this->_repoBonusCompress = $daoBonusCompress;
        $this->_repoBonusTypeCalc = $daoBonusTypeCalc;
        $this->_callBasePeriod = $callBasePeriod;
        $this->_callWalletOperation = $callWalletOperation;
        $this->_subBonus = $subBonus;
        $this->_subQualification = $subQual;
    }

    /**
     * @param $updates [$custId=>[$rankId=>$bonus, ...], ...]
     *
     * @return \Praxigento\Wallet\Service\Operation\Response\AddToWalletActive
     */
    private function _createBonusOperation($updates)
    {
        $asCustId = 'asCid';
        $asAmount = 'asAmnt';
        $asRef = 'asRef';
        $transData = [];
        foreach ($updates as $custId => $ranks) {
            foreach ($ranks as $rankId => $amount) {
                $item = [$asCustId => $custId, $asAmount => $amount, $asRef => $rankId];
                $transData[] = $item;
            }
        }
        $req = new WalletOperationAddToWalletActiveRequest();
        $req->setAsCustomerId($asCustId);
        $req->setAsAmount($asAmount);
        $req->setAsRef($asRef);
        $req->setOperationTypeCode(Cfg::CODE_TYPE_OPER_BONUS);
        $req->setTransData($transData);
        $result = $this->_callWalletOperation->addToWalletActive($req);
        return $result;
    }

    /**
     * @param Request\Bonus $req
     *
     * @return Response\Bonus
     */
    public function bonus(Request\Bonus $req)
    {
        $result = new Response\Bonus();
        $datePerformed = $req->getDatePerformed();
        $dateApplied = $req->getDateApplied();
        $this->logger->info("'Global Sales Bonus' calculation is started. Performed at: $datePerformed, applied at: $dateApplied.");
        $reqGetPeriod = new PeriodGetForDependentCalcRequest();
        $calcTypeBase = Cfg::CODE_TYPE_CALC_QUALIFICATION;
        $calcType = Cfg::CODE_TYPE_CALC_BONUS;
        $reqGetPeriod->setBaseCalcTypeCode($calcTypeBase);
        $reqGetPeriod->setDependentCalcTypeCode($calcType);
        $respGetPeriod = $this->_callBasePeriod->getForDependentCalc($reqGetPeriod);
        if ($respGetPeriod->isSucceed()) {
            $def = $this->_manTrans->begin();
            try {
                $periodDataDepend = $respGetPeriod->getDependentPeriodData();
                $calcDataDepend = $respGetPeriod->getDependentCalcData();
                $calcIdDepend = $calcDataDepend->getId();
                $dsBegin = $periodDataDepend->getDstampBegin();
                $dsEnd = $periodDataDepend->getDstampEnd();
                /* collect data to process bonus */
                $calcTypeIdCompress = $this->_repoBonusTypeCalc->getIdByCode(Cfg::CODE_TYPE_CALC_COMPRESSION);
                $calcDataCompress = $this->_repoBonusService
                    ->getLastCalcForPeriodByDates($calcTypeIdCompress, $dsBegin, $dsEnd);
                $calcIdCompress = $calcDataCompress->getId();
                $params = $this->_repoMod->getConfigParams();
                $treeCompressed = $this->_repoMod->getCompressedTreeWithQualifications($calcIdCompress);
                $pvTotal = $this->_repoMod->getSalesOrdersPvForPeriod($dsBegin, $dsEnd);
                /* calculate bonus */
                $updates = $this->_subBonus->calc($treeCompressed, $pvTotal, $params);
                /* create new operation with bonus transactions and save sales log */
                $respAdd = $this->_createBonusOperation($updates);
                $transLog = $respAdd->getTransactionsIds();
                $this->_repoMod->saveLogRanks($transLog);
                /* mark calculation as completed and finalize bonus */
                $this->_repoBonusService->markCalcComplete($calcIdDepend);
                $this->_manTrans->commit($def);
                $result->setPeriodId($periodDataDepend->getId());
                $result->setCalcId($calcIdDepend);
                $result->markSucceed();
            } finally {
                $this->_manTrans->end($def);
            }
        }
        $this->logger->info("'Global Sales Bonus' calculation is complete.");
        return $result;
    }

    public function qualification(Request\Qualification $req)
    {
        $result = new Response\Qualification();
        $datePerformed = $req->getDatePerformed();
        $dateApplied = $req->getDateApplied();
        $gvMaxLevels = $req->getGvMaxLevels();
        $msg = "'Qualification for Global Sales' calculation is started. "
            . "Performed at: $datePerformed, applied at: $dateApplied.";
        $this->logger->info($msg);
        $reqGetPeriod = new PeriodGetForDependentCalcRequest();
        $calcTypeBase = Cfg::CODE_TYPE_CALC_COMPRESSION;
        $calcType = Cfg::CODE_TYPE_CALC_QUALIFICATION;
        $reqGetPeriod->setBaseCalcTypeCode($calcTypeBase);
        $reqGetPeriod->setDependentCalcTypeCode($calcType);
        $respGetPeriod = $this->_callBasePeriod->getForDependentCalc($reqGetPeriod);
        if ($respGetPeriod->isSucceed()) {
            $def = $this->_manTrans->begin();
            try {
                $periodDataDepend = $respGetPeriod->getDependentPeriodData();
                $calcDataDepend = $respGetPeriod->getDependentCalcData();
                $calcIdDepend = $calcDataDepend->getId();
                $calcDataBase = $respGetPeriod->getBaseCalcData();
                $dsBegin = $periodDataDepend->getDstampBegin();
                $dsEnd = $periodDataDepend->getDstampEnd();
                $calcIdBase = $calcDataBase->getId();
                $tree = $this->_repoBonusCompress->getTreeByCalcId($calcIdBase);
                $qualData = $this->_repoMod->getQualificationData($dsBegin, $dsEnd);
                $cfgParams = $this->_repoMod->getConfigParams();
                $updates = $this->_subQualification->calcParams($tree, $qualData, $cfgParams, $gvMaxLevels);
                $this->_repoMod->saveQualificationParams($updates);
                $this->_repoBonusService->markCalcComplete($calcIdDepend);
                $this->_manTrans->commit($def);
                $result->setPeriodId($periodDataDepend->getId());
                $result->setCalcId($calcIdDepend);
                $result->markSucceed();
            } finally {
                $this->_manTrans->end($def);
            }
        }
        $this->logger->info("'Qualification for Global Sales' calculation is complete.");
        return $result;
    }
}