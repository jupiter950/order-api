<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

require('consts.php');

$container = new ContainerBuilder();

/** Pswd */
$container
    ->register('pswd', '\utils\Pswd');

/** HttpClient */
$container
    ->register('httpClient', '\utils\HttpClient');

/** Diablo */
$container
    ->register('diablo', '\fontbit\Diablo')
    ->addArgument(new Reference('httpClient'))
    ->addArgument(SERVER_END_POINT)
;

/** DB */
$container
    ->register('db', '\dl\DB')
    ->addArgument(DB_HOST)
    ->addArgument(DB_NAME)
    ->addArgument(DB_USER)
    ->addArgument(DB_PSWD)
;

/** Font */
$container
    ->register('font', '\dl\Font')
    ->addArgument(new Reference('db'))
;

/** Catalog */
$container
    ->register('catalog', '\dl\Catalog')
    ->addArgument(new Reference('db'))
;

/** Contact */
$container
    ->register('contact', '\dl\Contact')
    ->addArgument(new Reference('db'))
    ->addArgument(new Reference('pswd'))
;

/** Lead */
$container
    ->register('lead', '\dl\Lead')
    ->addArgument(new Reference('db'))
    ->addArgument(new Reference('contact'))
;

/** Sale */
$container
    ->register('sale', '\dl\Sale')
    ->addArgument(new Reference('db'))
;

/** LeadController */
$container
    ->register('controller.lead', '\controller\LeadController')
    ->addArgument(new Reference('lead'))
;

/** SaleController */
$container
    ->register('controller.sale', '\controller\SaleController')
    ->addArgument(new Reference('sale'))
    ->addArgument(new Reference('contact'))
    ->addArgument(new Reference('diablo'))
;

/** ContactController */
$container
    ->register('controller.contact', '\controller\ContactController')
    ->addArgument(new Reference('contact'))
    ->addArgument(new Reference('diablo'))
;





