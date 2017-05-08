<?php
require_once(__DIR__.'/vendor/autoload.php');
require_once(__DIR__.'/kiwi.php');
require_once(__DIR__.'/magicdiff.php');
use vielhuber\dbhelper\dbhelper;
use vielhuber\magicreplace\magicreplace;

if (!isset($argv) || empty($argv) || !isset($argv[1]) || !in_array($argv[1],['init','status','pull','push','rollback','clear','test','start','diff']))
{
    Kiwi::output('missing options',true);
}

if( $argv[1] == 'init' )
{
	Kiwi::init();
}

if( $argv[1] == 'status' )
{
	Kiwi::status();
}

if( $argv[1] == 'pull' )
{
	Kiwi::pull();
}

if( $argv[1] == 'push' )
{
	Kiwi::push();
}

if( $argv[1] == 'rollback' )
{
	Kiwi::rollback();
}

if( $argv[1] == 'clear' )
{
	Kiwi::clear();
}

if( $argv[1] == 'test' )
{
	Kiwi::test();
}

if( $argv[1] == 'start' )
{
    MagicDiff::start();
}

if( $argv[1] == 'diff' )
{
    MagicDiff::diff();
}