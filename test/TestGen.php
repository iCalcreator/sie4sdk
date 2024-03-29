<?php
/**
 * Sie4Sdk   PHP Sie4 SDK and Sie5 conversion package
 *
 * This file is a part of Sie4Sdk
 *
 * @author    Kjell-Inge Gustafsson, kigkonsult, <ical@kigkonsult.se>
 * @copyright 2021-2024 Kjell-Inge Gustafsson, kigkonsult, All rights reserved
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
use Kigkonsult\Sie4Sdk\Dto\DimDto;
use Kigkonsult\Sie4Sdk\Dto\VerDto;
use Kigkonsult\Sie4Sdk\DtoLoader\Sie4Dto as Sie4DtoLoader;
use Kigkonsult\Sie4Sdk\Util\StringUtil;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TestGen extends TestCase
{
    /**
     * genTest1/2 dataProvider
     *
     * Using phpunit php/var dirctive to set number of test sets, now 20
     *
     * @return array
     */
    public function genTestProvider() : array
    {
        $dataArr  = [];
        $max      = (int) $GLOBALS['GENTESTMAX'];

        for( $case = 1; $case <= $max; $case++ ) {
            $dataArr[] =
                [
                    $case++,
                    Sie4DtoLoader::load(),
                ];
        } // end for

        return $dataArr;
    }

    /**
     * Simple double create-string/parse-string test
     *
     * @test
     * @dataProvider genTestProvider
     *
     * @param int $case
     * @param Sie4Dto $sie4Dto
     * @return void
     * @throws Exception
     */
    public function genTest1( int $case, Sie4Dto $sie4Dto ) : void
    {
        static $ERR2 = '#%d-%d Sie4%sDto string compare error';
        $case += 100;
        // create cp437 sie4String
        $sie4String1 = Sie4EWriter::factory()->process( $sie4Dto );

        // parse the Sie4 string back and create new Sie4 string
        $sie4String2 = Sie4EWriter::factory()->process(
            Sie4Parser::factory()->process( $sie4String1 )
        );
        // parse the Sie4 string back and create new Sie4 string, compare
        $sie4String3 = Sie4EWriter::factory()->process(
            Sie4Parser::factory()->process( $sie4String2 )
        );
        // compare sie4 strings
        $this->assertEquals(
            StringUtil::cp437toUtf8( $sie4String1 ),
            StringUtil::cp437toUtf8( $sie4String3 ),
            sprintf( $ERR2, $case, 11, 'E' )
        );
    }

    /**
     * @test
     * @dataProvider genTestProvider
     *
     * Will most likely fail due to kontoTyp may not exist
     *
     * @param int $case
     * @param Sie4Dto $sie4Dto
     * @return void
     * @throws Exception
     */
    public function genTest2( int $case, Sie4Dto $sie4Dto ) : void
    {
        static $ERR1 = '#%d-%d Sie4%sDto assert error, %s%s';
        static $ERR2 = '#%d-%d Sie4%sDto string compare error';
        $case += 200;

        // SAVE here to solve sie5/sie5 disparity and to avoid last assert errors
        $fixes = [];
        $fixes[Sie4Dto::GENSIGN]  = $sie4Dto->getIdDto()->getSign() ?? Sie4Dto::PRODUCTNAME;
        $fixes[Sie4Dto::PROSA]    = $sie4Dto->getIdDto()->getProsa();
        $fixes[Sie4Dto::FTYP]     = $sie4Dto->getIdDto()->getFtyp();
        $fixes[Sie4Dto::ADRESS]   = $sie4Dto->getIdDto()->getAdress();
        $fixes[Sie4Dto::RAR]      = $sie4Dto->getIdDto()->getRarDtos();
        $fixes[Sie4Dto::TAXAR]    = $sie4Dto->getIdDto()->getTaxar();
        $fixes[Sie4Dto::KPTYPE]   = $sie4Dto->getIdDto()->getKptyp();
        $fixes[Sie4Dto::SRU]      = $sie4Dto->getSruDtos();
        $fixes[Sie4Dto::UNDERDIM] = $sie4Dto->getUnderDimDtos();

        if( empty( $case )) {
            // first only, save and read from file
            $tmpFilename = tempnam( sys_get_temp_dir(), __FUNCTION__ );
            Sie4EWriter::factory( $sie4Dto )->process( null, $tmpFilename, $sie4Dto->isKsummaSet() );
            $sie4Dto     = Sie4Parser::factory( $tmpFilename )->process();
            unlink( $tmpFilename );
        }

        // create utf8 sie4String
        $sie4String1 = StringUtil::cp437toUtf8(
            Sie4EWriter::factory()->process( $sie4Dto )
        );

        /*
        if( empty( $case )) {
            echo 'sie4Dto (case #' . $case . ') has ' . PHP_EOL .
                $sie4Dto->countAccountDtos()   . ' accontDtos'    . PHP_EOL .
                $sie4Dto->countDimDtos()       . ' dimDtos'       . PHP_EOL .
                $sie4Dto->countUnderDimDtos()  . ' underDimDtos'  . PHP_EOL .
                $sie4Dto->countDimObjektDtos() . ' dimObjektDtos' . PHP_EOL .
                $sie4Dto->countIbDtos()        . ' ibDtos'        . PHP_EOL .
                $sie4Dto->countUbDtos()        . ' ibDtos'        . PHP_EOL .
                $sie4Dto->countOibDtos()       . ' oibDtos'       . PHP_EOL .
                $sie4Dto->countOubDtos()       . ' oubDtos'       . PHP_EOL .
                $sie4Dto->countSaldoDtos()     . ' saldoDtos'     . PHP_EOL .
                $sie4Dto->countPsaldoDtos()    . ' pSaldoDtos'    . PHP_EOL .
                $sie4Dto->countPbudgetDtos()   . ' pBudgetDtos'   . PHP_EOL .
                $sie4Dto->countVerDtos()       . ' VerDtos with ' .
                $sie4Dto->countVerTransDtos()  . ' transDtos'     . PHP_EOL . PHP_EOL; // test ###
            echo 'sie4String1' . PHP_EOL . StringUtil::cp437toUtf8( $sie4String1 ) . PHP_EOL . PHP_EOL; // test ###
        }
        */

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
            sprintf( $ERR1, $case, 21, 'E', PHP_EOL, $sie4String1 )
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
            sprintf( $ERR2, $case, 22, 'E' )
        );

        // test convert to json and back and compare sie4Strings
        $jsonString = Sie4Dto2Json::process( $sie4Dto );
        $sie4Dto3    = Json2Sie4Dto::process( $jsonString );

        if( empty( $case )) {
            // echo $jsonString . PHP_EOL;
            // first only, test timestamp+guid, uniqueness in SieDto
            // also same in sie4Dto and sie4Dto3
            $this->checkTimeStampGuid4( $case, $sie4Dto, $sie4Dto3 );
            // check set fnrId, same in SieDto, IdDto, verDto and TransDto
            $this->checkFnrId5( $case, $sie4Dto );
            // check set orgnr, same in SieDto, IdDto, verDto and TransDto
            $this->checkOrgnr6( $case, $sie4Dto );
            // check serie/vernr, populatd down from VerDto to each TransDto
            $this->checkSerieVernr7( $case, $sie4Dto );
        }

        // check $sie4Dto/$sie4Dto3 strings
        $sie4String3 = Sie4EWriter::factory()->process( $sie4Dto3 );
        $this->assertEquals(
            $sie4String1,
            StringUtil::cp437toUtf8( $sie4String3 ),
            sprintf( $ERR2, $case, 23, 'E' )
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
        // compare
        $this->assertEquals(
            $sie4String1,
            $sie4String4,
            sprintf( $ERR2, $case, 25, 'E' )
        );

        // save test-file
        if( isset( $GLOBALS['TESTSAVEDIR'] )) {
            $path      = dirname( __DIR__ ) . DIRECTORY_SEPARATOR . $GLOBALS['TESTSAVEDIR'];
            if( ! is_dir( $path ) && ! mkdir( $path ) && ! is_dir( $path )) {
                throw new RuntimeException( sprintf( 'Directory "%s" was not created', $path ) );
            }
            $saveFileName = $path . DIRECTORY_SEPARATOR . __FUNCTION__ . $case;
            file_put_contents( $saveFileName . '.sie4E', $sie4String4 );
        } // end if save-file


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
                26,
                'I',
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

        // save test-file
        if( isset( $GLOBALS['TESTSAVEDIR'] )) {
            file_put_contents( $saveFileName . '.sie4I', $sie4String3 );
        } // end if save-file

        // parse Sie4IDto into SieEntry
        $sieEntry = Sie5EntryLoader::factory( $sie4Dto3 )->getSieEntry();
        $expected = [];
        // validate SieEntry
        $this->assertTrue(
            $sieEntry->isValid( $expected ),
            sprintf( $ERR1, $case, 27, 'I', PHP_EOL, var_export( $expected, true ) . PHP_EOL )
        );

        // parse SieEntry into Sie4
        $sie4IDto5 = Sie4ILoader::factory( $sieEntry )->getSie4IDto();
        if( $sie4Dto->isKsummaSet()) {
            $sie4IDto5->setKsumma( 1 );
        }

        // assert as Sie4I
        $outcome = true;
        try {
            Sie4Validator::assertSie4IDto( $sie4IDto5 );
        }
        catch( Exception $e ) {
            $outcome = $e->getMessage();
        }
        $this->assertTrue(
            $outcome,
            sprintf(
                $ERR1,
                $case,
                28,
                'I',
                PHP_EOL,
                StringUtil::cp437toUtf8(
                    Sie4EWriter::factory()->process( $sie4Dto3 ) // note Sie4EWriter
                )
            )
        );

        // fixes here to solve sie5/sie5 disparity and to avoid last assert errors
        $sie4IDto5->getIdDto()->setProgramnamn( Sie4Dto::PRODUCTNAME );
        $sie4IDto5->getIdDto()->setVersion( Sie4Dto::PRODUCTVERSION );
        $sie4IDto5->getIdDto()->setSign( $fixes[Sie4Dto::GENSIGN] );
        $sie4IDto5->getIdDto()->setProsa( $fixes[Sie4Dto::PROSA] );
        $sie4IDto5->getIdDto()->setFtyp( $fixes[Sie4Dto::FTYP] );
        $sie4IDto5->getIdDto()->setAdress( $fixes[Sie4Dto::ADRESS] );
        $sie4IDto5->getIdDto()->setRarDtos( $fixes[Sie4Dto::RAR] );
        $sie4IDto5->getIdDto()->setTaxar( $fixes[Sie4Dto::TAXAR] );
        $sie4IDto5->getIdDto()->setKptyp( $fixes[Sie4Dto::KPTYPE] );
        $sie4IDto5->setSruDtos( $fixes[Sie4Dto::SRU] );
        $sie4IDto5->setUnderDimDtos( $fixes[Sie4Dto::UNDERDIM] );

        // Remove non-mandatory #KTYP
        foreach( $sie4Dto3->getAccountDtos() as $accountDto ) {
            $accountDto->setKontoTyp();
        }
        $sie4String3 = StringUtil::cp437toUtf8(
            Sie4IWriter::factory()->process( $sie4Dto3 )
        );
        // Remove non-mandatory #KTYP
        foreach( $sie4IDto5->getAccountDtos() as $accountDto ) {
            $accountDto->setKontoTyp();
        }
        // write Sie4 string
        $sie4String5 = StringUtil::cp437toUtf8(
            Sie4IWriter::factory()->process( $sie4IDto5 )
        );

        // final compare of Sie4I AND Sie5 (SieEntry from Sie4I),
        // WILL return errors due to Sie4/Sie5 disparity, ex non-mandatory #KTYP
        $this->assertEquals(
            $sie4String3,
            $sie4String5,
            sprintf( $ERR2, $case, 29, 'I' )
        );
    }

    /**
     * test timestamp+guid, uniqueness in SieDto
     * check timestamps and guids - same in sie4Dto and sie4Dto3
     *
     * @param int     $case
     * @param Sie4Dto $expected
     * @param Sie4Dto $actual
     * @return void
     */
    public function checkTimeStampGuid4( int $case, Sie4Dto $expected, Sie4Dto $actual ) : void
    {
        static $ERR3 = '#%s-%d Sie4%sDto %s error, %s - %s';
        $tsGuidArr = [ $actual->getTimestamp() . $actual->getCorrelationId() ];
        $exp       = $expected->getTimestamp();
        $value     = $actual->getTimestamp();
        $this->assertEquals(
            $exp,
            $value,
            sprintf( $ERR3, $case, 41, 'E', Sie4Dto::TIMESTAMP, var_export( $exp, true ), var_export( $value, true ))
        );
        $exp   = $expected->getCorrelationId();
        $value = $actual->getCorrelationId();
        $this->assertEquals(
            $exp,
            $value,
            sprintf( $ERR3, $case, 42, 'E', Sie4Dto::GUID, $exp, $value )
        );
        $sie4DtoVerDtos = $expected->getVerDtos();
        foreach( $actual->getVerDtos() as $vx => $verDto ) {
            $testV = $vx + 50;
            $exp   = $sie4DtoVerDtos[$vx]->getTimestamp();
            $value = $verDto->getTimestamp();
            $this->assertEquals(
                $exp,
                $value,
                sprintf( $ERR3, $case, $testV, 'E', Sie4Dto::VERTIMESTAMP, $exp, (string) $value )
            );
            $exp   = $sie4DtoVerDtos[$vx]->getCorrelationId();
            $value = $verDto->getCorrelationId();
            $this->assertEquals(
                $exp,
                $value,
                sprintf( $ERR3, $case, $testV, 'E', Sie4Dto::VERGUID, $exp, $value )
            );

            $key   = $verDto->getTimestamp() . $verDto->getCorrelationId();
            $this->assertNotContains(
                $key, $tsGuidArr, sprintf( $ERR3, $case, $testV, 'E', VerDto::VER, $vx, $key )
            );
            $tsGuidArr[] = $key;

            $verTransDtos = $sie4DtoVerDtos[$vx]->getTransDtos();
            foreach( $verDto->getTransDtos() as $tx => $transDto ) {
                $testT = $testV . '-' . $tx;
                $exp   = $verTransDtos[$tx]->getTimestamp();
                $value = $transDto->getTimestamp();
                $this->assertEquals(
                    $exp,
                    $value,
                    sprintf( $ERR3, $case, $testT, 'E', Sie4Dto::TRANSTIMESTAMP, $exp, (string) $value )
                );
                $exp   = $verTransDtos[$tx]->getCorrelationId();
                $value = $transDto->getCorrelationId();
                $this->assertEquals(
                    $exp,
                    $value,
                    sprintf( $ERR3, $case, $testT, 'E', Sie4Dto::TRANSGUID, $exp, $value )
                );

                $key = $transDto->getTimestamp() . $transDto->getCorrelationId();
                $this->assertNotContains(
                    $key, $tsGuidArr, sprintf( $ERR3, $case, $testT, 'E', DimDto::TRANS, $vx . '-' . $tx, $key )
                );
                $tsGuidArr[] = $key;
            } // end foreach
        } // end foreach
    }

    /**
     * test setting fnrId in SieDto, must exist in each verDto and TransDto
     *
     * @param int     $case
     * @param Sie4Dto $sie4Dto
     * @return void
     */
    public function checkFnrId5( int $case, Sie4Dto $sie4Dto ) : void
    {
        static $ERR4  = '#%s-%d Sie4Dto %s %s fnrId error, %s - %s';
        static $FNRID = 'ABC';
        $case        .= '-FnrIdOrgnr-';
        $sie4Dto = clone $sie4Dto;
        $sie4Dto->setFnrId( $FNRID );
        $sie4Dto->setOrgnr( $FNRID ); // test ###

        $this->assertEquals(
            $FNRID,
            $sie4Dto->getFnrId(),
            sprintf(
                $ERR4,
                $case,
                51,
                '',
                '',
                $FNRID,
                $sie4Dto->getFnrId()
            )
        );
        $this->assertEquals(
            $FNRID,
            $sie4Dto->getIdDto()->getFnrId(),
            sprintf(
                $ERR4,
                $case,
                52,
                '',
                'IdDto',
                $FNRID,
                $sie4Dto->getIdDto()->getFnrId()
            )
        );

        foreach( $sie4Dto->getVerDtos() as $vx => $verDto ) {
            $this->assertEquals(
                $FNRID,
                $verDto->getFnrId(),
                sprintf(
                    $ERR4,
                    $case,
                    53,
                    $vx,
                    'VerDto',
                    $FNRID,
                    $sie4Dto->getIdDto()->getFnrId()
                )
            );
            foreach( $verDto->getTransDtos() as $tx => $transDto ) {
                $this->assertEquals(
                    $FNRID,
                    $transDto->getFnrId(),
                    sprintf(
                        $ERR4,
                        $case,
                        54,
                        $vx . '-' . $tx,
                        'TransDto',
                        $FNRID,
                        $sie4Dto->getIdDto()->getFnrId()
                    )
                );

                /*
                if( empty( $vx ) && empty( $tx )) {
                    echo var_export( $verDto, true ) . PHP_EOL; // test ###
                }
                */
            } // end foreach
        } // end foreach
    }

    /**
     * test setting orgnr in SieDto, must exist in each verDto and TransDto
     *
     * @param int     $case
     * @param Sie4Dto $sie4Dto
     * @return void
     */
    public function checkOrgnr6( int $case, Sie4Dto $sie4Dto ) : void
    {
        static $ERR4  = '#%s%d Sie4Dto %s %s orgnr error, %s - %s';
        static $ORGNR = 'ABCorgnr';
        static $MULTI = 2;
        $case        .= '-FnrIdOrgnr-';
        $sie4Dto = clone $sie4Dto;
        $sie4Dto->setOrgnr( $ORGNR );
        $sie4Dto->setMultiple( $MULTI );

        $orgnrM = $sie4Dto->getOrgnr() . $sie4Dto->getMultiple();
        $this->assertEquals(
            $ORGNR . $MULTI,
            $orgnrM,
            sprintf(
                $ERR4,
                $case,
                61,
                '',
                '',
                $ORGNR . $MULTI,
                $orgnrM
            )
        );
        $orgnrM = $sie4Dto->getIdDto()->getOrgnr() . $sie4Dto->getIdDto()->getMultiple();
        $this->assertEquals(
            $ORGNR . $MULTI,
            $orgnrM,
            sprintf(
                $ERR4,
                $case,
                62,
                '',
                'IdDto',
                $ORGNR . $MULTI,
                $orgnrM
            )
        );

        foreach( $sie4Dto->getVerDtos() as $vx => $verDto ) {
            $orgnrM = $verDto->getOrgnr() . $verDto->getMultiple();
            $this->assertEquals(
                $ORGNR . $MULTI,
                $orgnrM,
                sprintf(
                    $ERR4,
                    $case,
                    63,
                    $vx,
                    'VerDto',
                    $ORGNR . $MULTI,
                    $orgnrM
                )
            );
            foreach( $verDto->getTransDtos() as $tx => $transDto ) {
                $orgnrM = $transDto->getOrgnr() . $transDto->getMultiple();
                $this->assertEquals(
                    $ORGNR . $MULTI,
                    $orgnrM,
                    sprintf(
                        $ERR4,
                        $case,
                        64,
                        $vx . '-' . $tx,
                        'TransDto',
                        $ORGNR . $MULTI,
                        $orgnrM
                    )
                );
            } // end foreach
        } // end foreach
    }

    /**
     * test setting orgnr in SieDto, must exist in each verDto and TransDto
     *
     * @param int     $case
     * @param Sie4Dto $sie4Dto
     * @return void
     */
    public function checkSerieVernr7( int $case, Sie4Dto $sie4Dto ) : void
    {
        static $ERR5 = '#%s%d Sie4Dto %s %s serie/vernr error, %s - %s';
        $case       .= '-serieVernr';
        foreach( $sie4Dto->getVerDtos() as $vx => $verDto ) {
            $serie   = $verDto->isSerieSet() ? $verDto->getSerie() : StringUtil::$SP0;
            $vernr   = $verDto->isVernrSet() ? $verDto->getVernr() : StringUtil::$SP0;
            $exp     = $serie . $vernr;
            foreach( $verDto->getTransDtos() as $tx => $transDto ) {
                $actual = $transDto->getSerie() . $transDto->getVernr();
                $this->assertEquals(
                    $exp,
                    $actual,
                    sprintf(
                        $ERR5,
                        $case, 7,
                        $vx . '-' . $tx,
                        'TransDto',
                        $exp,
                        $actual
                    )
                );
            } // end foreach
        } // end foreach
    }
}
