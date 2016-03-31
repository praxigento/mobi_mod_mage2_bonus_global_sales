<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\GlobalSales\Lib\Service\Calc;

use Praxigento\Core\Lib\Context;

include_once(__DIR__ . '/../../phpunit_bootstrap.php');

class Call_ManualTest extends \Praxigento\Core\Lib\Test\BaseTestCase {

    public function test_bonus() {
        $obm = Context::instance()->getObjectManager();
        /** @var  $dba \Praxigento\Core\Lib\Context\IDbAdapter */
        $dba = $obm->get(\Praxigento\Core\Lib\Context\IDbAdapter::class);
        $dba->getDefaultConnection()->beginTransaction();
        /** @var  $call \Praxigento\Bonus\GlobalSales\Lib\Service\ICalc */
        $call = $obm->get(\Praxigento\Bonus\GlobalSales\Lib\Service\ICalc::class);
        $req = new Request\Bonus();
        $resp = $call->bonus($req);
        $this->assertTrue($resp->isSucceed());
        $dba->getDefaultConnection()->rollback();
    }

    public function test_qualification() {
        $obm = Context::instance()->getObjectManager();
        /** @var  $dba \Praxigento\Core\Lib\Context\IDbAdapter */
        $dba = $obm->get(\Praxigento\Core\Lib\Context\IDbAdapter::class);
        $dba->getDefaultConnection()->beginTransaction();
        /** @var  $call \Praxigento\Bonus\GlobalSales\Lib\Service\ICalc */
        $call = $obm->get(\Praxigento\Bonus\GlobalSales\Lib\Service\ICalc::class);
        $req = new Request\Qualification();
        $req->setGvMaxLevels(2);
        $resp = $call->qualification($req);
        $this->assertTrue($resp->isSucceed());
        $dba->getDefaultConnection()->rollback();
    }

}