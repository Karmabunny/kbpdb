<?php

use karmabunny\pdb\PdbConfig;
use PHPUnit\Framework\TestCase;



/**
 *
 */
class PdbHelperTests extends TestCase
{

    /** @var Pdb */
    public $pdb;


    public function setUp(): void
    {
        $this->pdb = Pdb::create([
            'type' => PdbConfig::TYPE_SQLITE,
            'dsn' => __DIR__ . '/db.sqlite',
        ]);
    }



    public function dataQueryType()
    {
        return [
        ];
    }


    /**
     * @dataProvider dataQueryType
     */
    public function testQueryType($val)
    {
        // TODO
    }


    public function dataLikeEscape()
    {
        return [
        ];
    }


    /**
     * @dataProvider dataLikeEscape
     */
    public function testLikeEscape($val)
    {
        // TODO
    }


    public function dataFieldEscape()
    {
        return [
        ];
    }


    /**
     * @dataProvider dataFieldEscape
     */
    public function testFieldEscape($val)
    {
        // TODO
    }


    public function dataParseAlias()
    {
        return [
        ];
    }


    /**
     * @dataProvider dataParseAlias
     */
    public function testParseAlias($val)
    {
        // TODO
    }



    /**
     * VALID DATA for testValidateAlias
     */
    public function dataValidateAliasValid()
    {
        return [
            // TODO
        ];
    }


    /**
     * @dataProvider dataValidateAliasValid
     */
    public function testValidateAliasValid($val)
    {
        // TODO
    }


    /**
     * INVALID DATA for testValidateAlias
     */
    public function dataValidateAliasInvalid()
    {
        return [
            // TODO
        ];
    }

    /**
     * @dataProvider dataValidateAliasValid
     */
    public function testValidateAliasInvalid($val)
    {
        // TODO
    }


    /**
     * VALID DATA for testValidateDirectionValid
     */
    public function dataValidateDirectionValid()
    {
        return [
            // TODO
        ];
    }

    /**
     * @dataProvider dataValidateDirectionValid
     */
    public function testValidateDirectionValid($val)
    {
        // TODO
    }


    /**
     * INVALID DATA for testValidateDirectionInvalid
     */
    public function dataValidateDirectionInvalid()
    {
        return [
            // TODO
        ];
    }

    /**
     * @dataProvider dataValidateDirectionInvalid
     */
    public function testValidateDirectionInvalid($val)
    {
        // TODO
    }


    /**
     * INVALID DATA for testValidateFunctionInvalid
     */
    public function dataValidateFunctionInvalid()
    {
        return [
            // TODO
        ];
    }

    /**
     * @dataProvider dataValidateFunctionInvalid
     */
    public function testValidateFunctionInvalid($val)
    {
        // TODO
    }


    /**
     * VALID DATA for testValidateFunctionValid
     */
    public function dataValidateFunctionValid()
    {
        return [
            // TODO
        ];
    }

    /**
     * @dataProvider dataValidateFunctionValid
     */
    public function testValidateFunctionValid($val)
    {
        // TODO
    }

}
