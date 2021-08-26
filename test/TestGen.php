<?php
/**
 * Sie4Sdk   PHP Sie4 SDK and Sie5 conversion package
 *
 * This file is a part of Sie4Sdk
 *
 * @author    Kjell-Inge Gustafsson, kigkonsult
 * @copyright 2021 Kjell-Inge Gustafsson, kigkonsult, All rights reserved
 * @link      https://kigkonsult.se
 * @license   Subject matter of licence is the software Sie4Sdk.
 *            The above package, copyright, link and this licence notice shall be
 *            included in all copies or substantial portions of the Sie4Sdk.
 *
 *            Sie4Sdk is free software: you can redistribute it and/or modify
 *            it under the terms of the GNU Lesser General Public License as
 *            published by the Free Software Foundation, either version 3 of
 *            the License, or (at your option) any later version.
 *
 *            Sie4Sdk is distributed in the hope that it will be useful,
 *            but WITHOUT ANY WARRANTY; without even the implied warranty of
 *            MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *            GNU Lesser General Public License for more details.
 *
 *            You should have received a copy of the GNU Lesser General Public License
 *            along with Sie4Sdk. If not, see <https://www.gnu.org/licenses/>.
 */
declare( strict_types = 1 );
namespace Kigkonsult\Sie4Sdk;

use Exception;
use Kigkonsult\Sie4Sdk\Api\Array2Sie4Dto;
use Kigkonsult\Sie4Sdk\Api\Json2Sie4Dto;
use Kigkonsult\Sie4Sdk\Api\Sie4Dto2Array;
use Kigkonsult\Sie4Sdk\Api\Sie4Dto2Json;
use Kigkonsult\Sie4Sdk\Dto\IdDto;
use Kigkonsult\Sie4Sdk\Dto\Sie4Dto;
use Kigkonsult\Sie4Sdk\DtoLoader\Sie4Dto as Sie4Gen;
use Kigkonsult\Sie4Sdk\Util\StringUtil;
use PHPUnit\Framework\TestCase;

class TestGen extends TestCase
{
    /**
     * genTest dataProvider
     *
     * @return mixed[]
     */
    public function genTestProvider() : array
    {
        $dataArr  = [];
        $max      = 10;
        $case     = 0;

        for( $x = 0; $x < $max; ++$x ) {
            $dataArr[] =
                [
                    $case++,
                    Sie4Gen::load(),
                ];
        } // end for

        return $dataArr;
    }

    /**
     * @test
     * @dataProvider genTestProvider
     *
     * @param int $case
     * @param Sie4Dto $sie4Dto
     * @return void
     */
    public function genTest( int $case, Sie4Dto $sie4Dto )
    {
        static $ERR1 = '#%d-%d Sie4%sDto assert error, %s%s%s';
        static $ERR2 = '#%d-%d Sie4%sDto string compare error';

        // create sie4String
        $sie4String1 = StringUtil::cp437toUtf8(
            Sie4EWriter::factory()->process( $sie4Dto )
        );

        if( empty( $case )) {
            echo 'sie4Dto (case #' . $case . ') has ' . PHP_EOL .
                $sie4Dto->countAccountDtos()   . ' accontDtos'    . PHP_EOL .
                $sie4Dto->countDimDtos()       . ' dimDtos'       . PHP_EOL .
                $sie4Dto->countUnderDimDtos()  . ' underDimDtos'  . PHP_EOL .
                $sie4Dto->countDimObjektDtos() . ' dimObjektDtos' . PHP_EOL .
                $sie4Dto->countIbDtos()        . ' ibDtos'        . PHP_EOL .
                $sie4Dto->countUbDtos()        . ' ibDtos'        . PHP_EOL .
                $sie4Dto->countOibDtos()       . ' oibDtos'      . PHP_EOL .
                $sie4Dto->countOubDtos()       . ' oubDtos'       . PHP_EOL .
                $sie4Dto->countSaldoDtos()     . ' saldoDtos'     . PHP_EOL .
                $sie4Dto->countPsaldoDtos()    . ' pSaldoDtos'    . PHP_EOL .
                $sie4Dto->countPbudgetDtos()   . ' pBudgetDtos'   . PHP_EOL .
                $sie4Dto->countVerDtos()       . ' VerDtos with ' .
                $sie4Dto->countVerTransDtos()  . ' transDtos'     . PHP_EOL; // test ###
            echo 'sie4String1' . PHP_EOL . StringUtil::cp437toUtf8( $sie4String1 ) . PHP_EOL; // test ###
        }

        // assert as Sie4E
        $outcome = true;
        try {
            Sie4Validator::assertSie4EDto( $sie4Dto );
        }
        catch( Exception $e ) {
            $outcome = $e->getMessage();
        }
        $this->assertTrue(
            $outcome,
            sprintf( $ERR1, $case, 1, 'E', $outcome, PHP_EOL, $sie4String1 )
        );

        // test convert to array and back and compare sie4Strings
        $sie4Array = Sie4Dto2Array::process( $sie4Dto );
        // echo var_export( $sie4Array ) . PHP_EOL; // test ###
        $sie4Dto2  = Array2Sie4Dto::process( $sie4Array );
        $this->assertEquals(
            $sie4String1,
            StringUtil::cp437toUtf8(
                Sie4EWriter::factory()->process( $sie4Dto2 )
            ),
            sprintf( $ERR2, $case, 2, 'E' )
        );

        // test convert to json and back and compare sie4Strings
        $jsonString = Sie4Dto2Json::process( $sie4Dto );
        // echo $jsonString . PHP_EOL;
        $sie4Dto3    = Json2Sie4Dto::process( $jsonString );
        $sie4String3 = Sie4EWriter::factory()->process( $sie4Dto3 );
        $this->assertEquals(
            $sie4String1,
            StringUtil::cp437toUtf8( $sie4String3 ),
            sprintf( $ERR2, $case, 3, 'E' )
        );

        // parse the Sie4E string back and create new Sie4E string, compare
        $sie4String4 = StringUtil::cp437toUtf8(
            Sie4EWriter::factory()->process(
                Sie4Parser::factory()->process( $sie4String3 )
            )
        );
        // skip opt KSUMMA
        if( $sie4Dto->isKsummaSet()) {
            $sie4String1 = StringUtil::beforeLast( Sie4Dto::KSUMMA, $sie4String1 );
            $sie4String4 = StringUtil::beforeLast( Sie4Dto::KSUMMA, $sie4String4 );
        }
        $this->assertEquals(
            $sie4String1,
            $sie4String4,
            sprintf( $ERR2, $case, 4, 'E' )
        );

        // prep as Sie4I
        $idDto1 = $sie4Dto3->getIdDto();
        $idDto2 = new IdDto();
        $idDto2->setProsa( $idDto1->getProsa());
        $idDto2->setFtyp( $idDto1->getFtyp());
        $idDto2->setFnrId( $idDto1->getFnrId());
        $idDto2->setOrgnr( $idDto1->getOrgnr());
        // skip Bkod
        $idDto2->setMultiple( $idDto1->getMultiple());
        $idDto2->setAdress( $idDto1->getAdress());
        $idDto2->setFnamn( $idDto1->getFnamn());
        $idDto2->setRarDtos( $idDto1->getRarDtos());
        $idDto2->setTaxar( $idDto1->getTaxar());
        // skip omfattn
        $idDto2->setKptyp( $idDto1->getKptyp());
        $idDto2->setValutakod( $idDto1->getValutakod());
        $sie4Dto3->setIdDto( $idDto2 );

        $sie4Dto3->setIbDtos( [] );
        $sie4Dto3->setUbDtos( [] );
        $sie4Dto3->setOibDtos( [] );
        $sie4Dto3->setOubDtos( [] );
        $sie4Dto3->setSaldoDtos( [] );
        $sie4Dto3->setPsaldoDtos( [] );
        $sie4Dto3->setPbudgetDtos( [] );

        // assert as Sie4I
        $outcome = true;
        try {
            Sie4Validator::assertSie4IDto( $sie4Dto3 );
        }
        catch( Exception $e ) {
            $outcome = $e->getMessage();
        }
        $this->assertTrue(
            $outcome,
            sprintf(
                $ERR1,
                $case,
                5,
                'I',
                $outcome,
                PHP_EOL,
                StringUtil::cp437toUtf8(
                    Sie4EWriter::factory()->process( $sie4Dto3 ) // note Sie4EWriter
                )
            )
        );

        // write Sie4 string
        $sie4String3 = StringUtil::cp437toUtf8(
            Sie4IWriter::factory()->process( $sie4Dto3 )
        );

        // parse Sie4IDto into SieEntry
        $sieEntry = Sie5EntryLoader::factory( $sie4Dto3 )->getSieEntry();
        $expected = [];
        // validate SieEntry
        $this->assertTrue(
            $sieEntry->isValid( $expected ),
            sprintf( $ERR1, $case, 5, 'I', '', PHP_EOL, var_export( $expected, true ), PHP_EOL )
        );

        // parse SieEntry into Sie4
        $sie4IDto5 = Sie4ILoader::factory(  $sieEntry )->getSie4IDto();
        if( $sie4Dto->isKsummaSet()) {
            $sie4IDto5->setKsumma( 1 );
        }

        // assert as Sie4I
        $outcome = true;
        try {
            Sie4Validator::assertSie4IDto( $sie4Dto3 );
        }
        catch( Exception $e ) {
            $outcome = $e->getMessage();
        }
        $this->assertTrue(
            $outcome,
            sprintf(
                $ERR1,
                $case,
                6,
                'I',
                $outcome,
                PHP_EOL,
                StringUtil::cp437toUtf8(
                    Sie4EWriter::factory()->process( $sie4Dto3 ) // note Sie4EWriter
                )
            )
        );

        // write Sie4 string
        $sie4String5 = StringUtil::cp437toUtf8(
            Sie4IWriter::factory()->process( $sie4IDto5 )
        );

        // final compare
        $this->assertEquals(
            $sie4String3,
            $sie4String5,
            sprintf( $ERR2, $case, 7, 'I' )
        );
    }
}