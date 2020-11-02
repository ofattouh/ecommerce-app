<?php

// autoload_psr4.php @generated by Composer

$vendorDir = dirname(dirname(__FILE__));
$baseDir = dirname($vendorDir);

return array(
    'Psr\\Container\\' => array($vendorDir . '/psr/container/src'),
    'Interop\\Container\\' => array($vendorDir . '/container-interop/container-interop/src/Interop/Container', $vendorDir . '/container-interop/service-provider/src'),
    'Dhii\\Stats\\' => array($vendorDir . '/dhii/stats-interface/src', $vendorDir . '/dhii/stats-abstract/src'),
    'Dhii\\Di\\' => array($vendorDir . '/dhii/di-interface/src', $vendorDir . '/dhii/di-abstract/src', $vendorDir . '/dhii/di/src'),
    'Dhii\\Collection\\' => array($vendorDir . '/dhii/collections-interface/src', $vendorDir . '/dhii/collections-abstract-base/src', $vendorDir . '/dhii/collections-abstract/src'),
);
