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

use InvalidArgumentException;
use Kigkonsult\Asit\It;
use Kigkonsult\Sie4Sdk\Dto\AdressDto;
use Kigkonsult\Sie4Sdk\Dto\BalansDto;
use Kigkonsult\Sie4Sdk\Dto\BalansObjektDto;
use Kigkonsult\Sie4Sdk\Dto\IdDto;
use Kigkonsult\Sie4Sdk\Dto\PeriodDto;
use Kigkonsult\Sie4Sdk\Dto\RarDto;
use Kigkonsult\Sie4Sdk\Dto\Sie4Dto;
use Kigkonsult\Sie4Sdk\Dto\TransDto;
use Kigkonsult\Sie4Sdk\Dto\VerDto;
use Kigkonsult\Sie4Sdk\Util\ArrayUtil;
use Kigkonsult\Sie4Sdk\Util\DateTimeUtil;
use Kigkonsult\Sie4Sdk\Util\FileUtil;
use Kigkonsult\Sie4Sdk\Util\StringUtil;
use RuntimeException;

use function array_map;
use function array_slice;
use function current;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function ksort;
use function sprintf;
use function trim;

/**
 * Class Sie4Parser
 *
 * Parse Sie4 file/string into Sie4IDto
 */
class Sie4Parser implements Sie4Interface
{
    /**
     * posterna f??rekommer i f??ljande ordning:
     * 1 Flaggpost
     * 2 Identifikationsposter
     * 3 Kontoplansuppgifter
     * 4 Saldoposter/Verifikationsposter
     */

    /**
     * Identifikationsposter
     *
     * @var string[]  may NOT occur in order in Sie4
     */
    protected static array $IDLABELS = [
        self::PROGRAM,
        self::FORMAT,
        self::GEN,
        self::SIETYP,
        self::PROSA,
        self::FTYP,
        self::FNR,
        self::ORGNR,
        self::BKOD,
        self::ADRESS,
        self::FNAMN,
        self::RAR,
        self::TAXAR,
        self::OMFATTN,
        self::KPTYP,
        self::VALUTA,
    ];

    /**
     * Kontoplansuppgifter
     *
     * @var string[]  may NOT occur in order in Sie4
     */
    protected static array $ACCOUNTLABELS = [
        self::KONTO,
        self::KTYP,
        self::ENHET,
        self::SRU,
        self::DIM,
        self::UNDERDIM,
        self::OBJEKT,
    ];

    /**
     * Saldoposter
     *
     * @var string[]  may NOT occur in order in Sie4
     */
    protected static array $SUMMARYLABELS = [
        self::IB,
        self::UB,
        self::OIB,
        self::OUB,
        self::RES,
        self::PSALDO,
        self::PBUDGET
    ];

    /**
     * Verifikationsposter
     *
     * @var string[]  may NOT occur in order in Sie4
     */
    protected static array $LEDGERENTRYLABELS = [
        self::VER,
        self::TRANS,
        self::RTRANS,
        self::BTRANS,
    ];

    /**
     * Input file rows, managed by Asit\It
     *
     * @var It|null
     */
    private ?It $input = null;

    /**
     * @var Sie4Dto|null
     */
    private ?Sie4Dto $sie4Dto = null;

    /**
     * Current VerDto, 'parent' for TransDto's
     *
     * @var VerDto|null
     */
    private ?VerDto $currentVerDto = null;

    /**
     * @var array
     */
    private array $postGroupActions = [];

    /**
     * Return instance
     *
     * @param string|string[]|null $source
     * @return self
     */
    public static function factory( array | string $source = null ) : self
    {
        $instance = new self();
        if( ! empty( $source )) {
            $instance->setInput( $source );
        }
        return $instance;
    }

    /**
     * Set input from Sie4 file, -array, -string
     *
     * @param string|string[] $source
     * @return self
     * @throws InvalidArgumentException
     */
    public function setInput( array | string $source ) : self
    {
        static $TRIM      = [ StringUtil::class, 'trimString' ];
        static $TAB2SPACE = [ StringUtil::class, 'tab2Space' ];
        static $FMT1      = 'Unvalid source';
        if( is_array( $source )) {
            $input = $source;
        }
        else {
            if( ! is_string( $source )) {
                throw new InvalidArgumentException( $FMT1, 1111 );
            }
            $source = trim( $source );
            if( ! StringUtil::startsWith( $source, self::FLAGGA )) {
                FileUtil::assertReadFile( $source, 1112 );
                $input = FileUtil::readFile( $source, 1113 );
            }
            else {
                $input = StringUtil::string2Arr(
                    StringUtil::convEolChar( $source )
                );
            }
        } // end else
        $fileRows = new It(
            array_map( $TRIM, array_map( $TAB2SPACE, $input ))
        );
        Sie4Validator::assertSie4Input( $fileRows );
        $this->input     = $fileRows;
        return $this;
    }

    /**
     * Parse Sie4, opt input from Sie4 file, -array (rows), -string, return sie4Dto
     *
     * @param null|string|string[] $source
     * @return Sie4Dto
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @deprecated
     */
    public function parse4I( null|string|array $source = null ) : Sie4Dto
    {
        return $this->process( $source );
    }

    /**
     * Parse Sie4/Sie4E, opt input from Sie4 file, -array (rows), -string, return sie4Dto
     *
     * @param null|string|string[] $source
     * @return Sie4Dto
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function process( null|string|array $source = null ) : Sie4Dto
    {
        static $FMT1      = 'Input error (#%d) on post %s';
        static $GROUP12   = [ 1, 2 ];
        static $GROUP23   = [ 2, 3 ];
        static $GROUP234  = [ 2, 3, 4 ];
        static $GROUP2345 = [ 2, 3, 4, 5 ];
        if( ! empty( $source )) {
            $this->setInput( $source );
        }
        $this->sie4Dto  = new Sie4Dto();
        $this->input->rewind();
        $currentGroup   = 0;
        $kSummaCounter  = 0;
        $prevLabel      = null;
        $this->postGroupActions = [];
        while( $this->input->valid()) {
            $row = (string) $this->input->current();
            if( empty( $row )) {
                $this->input->next();
                continue;
            }
            $row = StringUtil::cp437toUtf8( $row );
            [ $label, $rowData ] = StringUtil::splitPost( $row );
            switch( true ) {
                case (( 0 === $currentGroup ) && ( self::FLAGGA === $label )) :
                    ArrayUtil::assureArrayLength( $rowData, 1 );
                    $this->sie4Dto->setFlagga((int) $rowData[0] );
                    $currentGroup = 1;
                    break;
                case (( 0 < $currentGroup ) && ( self::KSUMMA === $label )) :
                    ++$kSummaCounter;
                    if( 2 === $kSummaCounter ) {
                        ArrayUtil::assureArrayLength( $rowData, 1 );
                        $this->sie4Dto->setKsumma((int) $rowData[0] );
                    }
                    break;

                case ( in_array( $currentGroup, $GROUP12, true )
                    && in_array( $label, self::$IDLABELS, true )) :
                    $currentGroup = 2;
                    $this->readIdData( $label, $rowData );
                    break;
                case (( 2 === $currentGroup ) && empty( $label )) :
                    // data content for previous Label
                    $this->readIdData( $prevLabel, $rowData );
                    break;

                case ( in_array( $currentGroup, $GROUP23, true )
                    && in_array( $label, self::$ACCOUNTLABELS, true )) :
                    if( 2 === $currentGroup ) {
                        // finish off opt group 2 actions
                        $this->postReadGroupAction();
                        $currentGroup = 3;
                    }
                    $this->readAccountData( $label, $rowData );
                    break;
                case (( 3 === $currentGroup ) && empty( $label )) :
                    // data content for previous Label
                    $this->readAccountData( $prevLabel, $rowData );
                    break;

                case ( in_array( $currentGroup, $GROUP234, true )
                    && in_array( $label, self::$SUMMARYLABELS, true )) :
                    if( in_array( $currentGroup, $GROUP23, true )) {
                        // finish off opt group (2-)3 actions
                        $this->postReadGroupAction();
                        $currentGroup = 4;
                    }
                    $this->readSummaryData( $label, $rowData );
                    break;

                case ( in_array( $currentGroup, $GROUP2345, true )
                    && in_array( $label, self::$LEDGERENTRYLABELS, true )) :
                    if( in_array( $currentGroup, $GROUP234, true )) {
                        // finish off opt group (2-3-)4 actions
                        $this->postReadGroupAction();
                        $currentGroup = 5;
                    }
                    $this->readVerTransData( $label, $rowData );
                    break;
                case (( 5 === $currentGroup ) && empty( $label )) :
                    // data content for previous Label
                    $this->readVerTransData( $prevLabel, $rowData );
                    break;

                default :
                    throw new RuntimeException( sprintf( $FMT1, 1, $row ), 1411 );
            } // end switch
            if( ! empty( $label )) {
                $prevLabel = $label;
            }
            $this->input->next();
        } // end while
        if( ! empty( $this->postGroupActions )) {
            // finish off opt group 5 actions
            $this->postReadGroupAction();
        }
        return $this->sie4Dto;
    }

    /**
     * Manage Sie4 'Identifikationsposter'
     *
     * Note f??r #GEN
     *   if 'sign' is missing, '#PROGRAM programnamn' will be used in Sie4IWriter
     *
     * @param string $label
     * @param string[] $rowData
     * @return void
     * @throws RuntimeException
     */
    private function readIdData( string $label, array $rowData ) : void
    {
        if( ! $this->sie4Dto->isIdDtoSet()) {
            $this->sie4Dto->setIdDto( new IdDto());
        }
        $idDto = $this->sie4Dto->getIdDto();
        switch( $label ) {
            case self::PROGRAM :
                self::processProgram( $rowData, $idDto );
                break;
            /**
             * Vilken teckenupps??ttning som anv??nts
             *
             * Obligatorisk men auto-set
             * #FORMAT PC8
             * SKA vara IBM PC 8-bitars extended ASCII (Codepage 437)
             * https://en.wikipedia.org/wiki/Code_page_437
             */
            case self::FORMAT :
                break;
            case self::GEN :
                self::processGen( $rowData, $idDto );
                break;
            case self::SIETYP :
                self::processSieTyp( $rowData, $idDto );
                break;
            case self::PROSA :
                self::processProsa( $rowData, $idDto );
                break;
            case self::FTYP :
                self::processFtyp( $rowData, $idDto );
                break;
            case self::FNR :
                self::processFnr( $rowData, $idDto );
                break;
            case self::ORGNR :
                self::processOrgnr( $rowData, $idDto );
                break;
            case self::BKOD :
                self::processBkod( $rowData, $idDto );
                break;
            case self::ADRESS :
                self::processAdress( $rowData, $idDto );
                break;
            case self::FNAMN :
                self::processFnamn( $rowData, $idDto );
                break;
            case self::RAR :
                self::processRar( $rowData, $idDto );
                break;
            case self::TAXAR :
                self::processTaxar( $rowData, $idDto );
                break;
            case self::OMFATTN :
                self::processOmfattn( $rowData, $idDto );
                break;
            case self::KPTYP :
                self::processKtyp( $rowData, $idDto );
                break;
            case self::VALUTA :
                self::processValuta( $rowData, $idDto );
                break;
        } // end switch
    }

    /**
     * Vilket program som genererat filen
     *
     * Obligatorisk
     * #PROGRAM programnamn version
     *
     * Rowdata kan ha fler ??n 2 element, isf ta sista som version,
     *
     * @param string[] $rowData
     * @param IdDto    $idDto
     * @return void
     */
    private static function processProgram( array $rowData, IdDto $idDto ) : void
    {
        ArrayUtil::assureArrayLength( $rowData, 2 );
        $version    = current( array_slice( $rowData, -1, 1 ));
        $name       = implode( StringUtil::$SP1, array_slice( $rowData, 0, -1 ));
        $idDto->setProgramnamn( $name );
        $idDto->setVersion( $version );
    }


    /**
     * N??r och av vem som filen genererats
     *
     * #GEN datum sign
     * Obligatorisk (sign opt) Sie4, b??da obl. Sie5 SieEntry
     *
     * @param string[] $rowData
     * @param IdDto    $idDto
     * @return void
     */
    private static function processGen( array $rowData, IdDto $idDto ) : void
    {
        ArrayUtil::assureArrayLength( $rowData, 2 );
        $idDto->setGenDate(
            DateTimeUtil::getDateTime(
                $rowData[0],
                self::GEN,
                1511
            )
        );
        if( ! empty( $rowData[1] )) {
            $idDto->setSign( $rowData[1] );
        }
    }

    /**
     * Vilken typ av SIE-formatet filen f??ljer
     *
     * #SIETYP typnr
     * SKA vara 4, tidigare evaluerat
     * Obligatorisk men default 4
     *
     * @param string[] $rowData
     * @param IdDto $idDto
     * @return void
     */
    private static function processSieTyp( array $rowData, IdDto $idDto ) : void
    {
        ArrayUtil::assureArrayLength( $rowData, 1 );
        $idDto->setSieTyp( $rowData[0] );
    }

    /**
     * Fri kommentartext kring filens inneh??ll
     *
     * #PROSA text
     * valfri
     *
     * @param string[] $rowData
     * @param IdDto    $idDto
     * @return void
     */
    private static function processProsa( array $rowData, IdDto $idDto ) : void
    {
        $idDto->setProsa( trim( implode( StringUtil::$SP1, $rowData )));
    }

    /**
     * F??retagstyp
     *
     * #FTYP F??retagstyp
     * valfri
     *
     * @param string[] $rowData
     * @param IdDto $idDto
     * @return void
     */
    private static function processFtyp( array $rowData, IdDto $idDto ) : void
    {
        ArrayUtil::assureArrayLength( $rowData, 1 );
        $idDto->setFtyp( $rowData[0] );
    }

    /**
     * Redovisningsprogrammets internkod f??r exporterat f??retag
     *
     * #FNR f??retagsid
     * valfri
     *
     * @param string[] $rowData
     * @param IdDto    $idDto
     * @return void
     */
    private static function processFnr( array $rowData, IdDto $idDto ) : void
    {
        ArrayUtil::assureArrayLength( $rowData, 1 );
        $idDto->setFnrId( $rowData[0] );
    }

    /**
     * Organisationsnummer f??r det f??retag som exporterats
     *
     * #ORGNR orgnr f??rvnr verknr
     * f??rvnr : anv d?? ensk. person driver flera ensk. firmor (ordningsnr)
     * verknr : anv ej
     * valfri, MEN orgnr obligatoriskt i sie4Dto (FileInfoTypeEntry/CompanyTypeEntry)
     *
     * @param string[] $rowData
     * @param IdDto    $idDto
     * @return void
     */
    private static function processOrgnr( array $rowData, IdDto $idDto ) : void
    {
        ArrayUtil::assureArrayLength( $rowData, 2 );
        $idDto->setOrgnr( $rowData[0] );
        $idDto->setMultiple(
            ( ! empty( $rowData[1] ))
                ? (int)$rowData[1] :
                1
        );
    }

    /**
     * Branschtillh??righet f??r det exporterade f??retaget, Sie4E only
     *
     * #BKOD SNI-kod
     *
     * @param string[] $rowData
     * @param IdDto    $idDto
     * @return void
     */
    private static function processBkod( array $rowData, IdDto $idDto ) : void
    {
        ArrayUtil::assureArrayLength( $rowData, 1 );
        $idDto->setBkod( $rowData[0] );
    }

    /**
     * Adressuppgifter f??r det aktuella f??retaget
     *
     * #ADRESS kontakt utdelningsadr postadr tel
     * valfri
     *
     * @param string[] $rowData
     * @param IdDto    $idDto
     * @return void
     */
    private static function processAdress( array $rowData, IdDto $idDto ) : void
    {
        ArrayUtil::assureArrayLength( $rowData, 4 );
        $idDto->setAdress(
            AdressDto::factory(
                $rowData[0],
                $rowData[1],
                $rowData[2],
                $rowData[3]
            )
        );
    }

    /**
     * Fullst??ndigt namn f??r det f??retag som exporterats
     *
     * #FNAMN f??retagsnamn
     * Obligatorisk men valfri i sie4Dto (FileInfoTypeEntry/CompanyTypeEntry)
     *
     * @param string[] $rowData
     * @param IdDto    $idDto
     * @return void
     */
    private static function processFnamn( array $rowData, IdDto $idDto ) : void
    {
        $idDto->setFnamn( trim( implode( StringUtil::$SP1, $rowData )));
    }

    /**
     * R??kenskaps??r fr??n vilket exporterade data h??mtats
     *
     * #RAR ??rsnr start slut
     * valfri
     *
     * @param string[] $rowData
     * @param IdDto    $idDto
     * @return void
     */
    private static function processRar( array $rowData, IdDto $idDto ) : void
    {
        ArrayUtil::assureArrayLength( $rowData, 3 );
        $idDto->addRarDto(
            RarDto::factory(
                $rowData[0],
                DateTimeUtil::getDateTime(
                    $rowData[1],
                    self::RAR,
                    1517
                ),
                DateTimeUtil::getDateTime(
                    $rowData[2],
                    self::RAR,
                    1518
                )
            )
        );
    }

    /**
     * Taxerings??r f??r deklarations- information (SRU-koder)
     *
     * #TAXAR ??r
     * valfri
     *
     * @param string[] $rowData
     * @param IdDto    $idDto
     * @return void
     */
    private static function processTaxar( array $rowData, IdDto $idDto ) : void
    {
        ArrayUtil::assureArrayLength( $rowData, 1 );
        $idDto->setTaxar( $rowData[0] );
    }

    /**
     * Datum f??r periodsaldons omfattning
     *
     * #OMFATTN datum
     * valfri, Sie4E only
     *
     * @param string[] $rowData
     * @param IdDto    $idDto
     * @return void
     */
    private static function processOmfattn( array $rowData, IdDto $idDto ) : void
    {
        ArrayUtil::assureArrayLength( $rowData, 1 );
        $idDto->setOmfattn(
            DateTimeUtil::getDateTime(
                $rowData[0],
                self::OMFATTN,
                1519
            )
        );
    }

    /**
     * Kontoplanstyp
     *
     * #KPTYP typ
     * valfri
     *
     * @param string[] $rowData
     * @param IdDto    $idDto
     * @return void
     */
    private static function processKtyp( array $rowData, IdDto $idDto ) : void
    {
        ArrayUtil::assureArrayLength( $rowData, 1 );
        $idDto->setKptyp( $rowData[0] );
    }

    /**
     * Redovisningsvaluta
     *
     * #VALUTA valutakod
     * valfri
     *
     * @param string[] $rowData
     * @param IdDto    $idDto
     * @return void
     */
    private static function processValuta( array $rowData, IdDto $idDto ) : void
    {
        ArrayUtil::assureArrayLength( $rowData, 1 );
        $idDto->setValutakod( $rowData[0] );
    }

    /**
     * Manage Sie4  'Kontoplansuppgifter'
     *
     * #UNDERDIM are skipped
     * #KONTO etc and #DIM/#OBJEKT : prepare for postGroup actions
     *
     * @param string $label
     * @param string[] $rowData
     * @return void
     */
    private function readAccountData( string $label, array $rowData ) : void
    {
        switch( $label ) {
            case self::KONTO :
                $this->processKonto( $rowData );
                break;
            case self::KTYP :
                $this->processKontotyp( $rowData );
                break;
            case self::ENHET :
                $this->processEnhet( $rowData );
                break;
            case self::SRU :
                $this->processSru( $rowData );
                break;
            case self::DIM :
                $this->processDim( $rowData );
                break;
            case self::UNDERDIM :
                $this->processUnderDim( $rowData );
                break;
            case self::OBJEKT :
                $this->processObject( $rowData );
                break;
        } // end switch
    }

    /**
     * Kontouppgifter
     *
     * #KONTO kontonr kontoNamn
     * valfri
     *
     * @param string[] $rowData
     * @return void
     */
    private function processKonto( array $rowData ) : void
    {
        ArrayUtil::assureArrayLength( $rowData, 2 );
        ArrayUtil::assureIsArray(
            $this->postGroupActions,
            self::KONTO
        );
        [ $kontonr, $kontonamn ] = $rowData;
        ArrayUtil::assureIsArray(
            $this->postGroupActions[self::KONTO],
            $kontonr
        );
        $this->postGroupActions[self::KONTO][$kontonr][0] = $kontonamn;
    }

    /**
     * Kontotyp
     *
     * #KTYP kontonr  kontoTyp
     * valfri
     *
     * @param string[] $rowData
     * @return void
     */
    private function processKontotyp( array $rowData ) : void
    {
        ArrayUtil::assureArrayLength( $rowData, 2 );
        ArrayUtil::assureIsArray(
            $this->postGroupActions,
            self::KONTO
        );
        [ $kontonr, $kontotyp ] = $rowData;
        ArrayUtil::assureIsArray(
            $this->postGroupActions[self::KONTO],
            $kontonr
        );
        $this->postGroupActions[self::KONTO][$kontonr][1] = $kontotyp;
    }

    /**
     * Enhet vid kvantitetsredovisning
     *
     * #ENHET kontonr enhet
     * valfri
     *
     * @param string[] $rowData
     * @return void
     */
    private function processEnhet( array $rowData ) : void
    {
        ArrayUtil::assureArrayLength( $rowData, 2 );
        ArrayUtil::assureIsArray(
            $this->postGroupActions,
            self::KONTO
        );
        [ $kontonr, $enhet ] = $rowData;
        ArrayUtil::assureIsArray(
            $this->postGroupActions[self::KONTO],
            $kontonr
        );
        $this->postGroupActions[self::KONTO][$kontonr][2] = $enhet;
    }

    /**
     * RSV-kod f??r standardiserat r??kenskapsutdrag
     *
     * #SRU konto SRU-kod
     * valfri
     *
     * @param string[] $rowData
     * @return void
     */
    private function processSru( array $rowData ) : void
    {
        ArrayUtil::assureArrayLength( $rowData, 2 );
        $this->sie4Dto->addSru( $rowData[0], $rowData[1] );
    }

    /**
     * Dimension
     *
     * #DIM dimensionsnr namn
     * valfri
     *
     * @param string[] $rowData
     * @return void
     */
    private function processDim( array $rowData ) : void
    {
        ArrayUtil::assureArrayLength( $rowData, 2 );
        ArrayUtil::assureIsArray(
            $this->postGroupActions,
            self::DIM
        );
        [ $dimensionsnr, $namn ] = $rowData;
        ArrayUtil::assureIsArray(
            $this->postGroupActions[self::DIM],
            $dimensionsnr
        );
        $this->postGroupActions[self::DIM][$dimensionsnr][0] = $namn;
    }

    /**
     * UnderDim
     *
     * #UNDERDIM dimensionsnr namn superdimension
     * valfri
     *
     * @param string[] $rowData
     * @return void
     */
    private function processUnderDim( array $rowData ) : void
    {
        ArrayUtil::assureArrayLength( $rowData, 3 );
        ArrayUtil::assureIsArray(
            $this->postGroupActions,
            self::DIM
        );
        [ $dimensionsnr, $namn, $superDimNr ] = $rowData;
        ArrayUtil::assureIsArray(
            $this->postGroupActions[self::DIM],
            $superDimNr
        );
        ArrayUtil::assureIsArray(
            $this->postGroupActions[self::DIM][$superDimNr],
            self::UNDERDIM
        );
        $this->postGroupActions[self::DIM][$superDimNr][self::UNDERDIM][$dimensionsnr] = $namn;
    }

    /**
     * Objekt
     *
     * #OBJEKT dimensionsnr objektnr objektnamn
     * valfri
     *
     * @param string[] $rowData
     * @return void
     */
    private function processObject( array $rowData ) : void
    {
        ArrayUtil::assureArrayLength( $rowData, 3 );
        ArrayUtil::assureIsArray(
            $this->postGroupActions,
            self::DIM
        );
        [ $dimensionsnr, $objektnr, $objeknamn ] = $rowData;
        ArrayUtil::assureIsArray(
            $this->postGroupActions[self::DIM],
            $dimensionsnr
        );
        ArrayUtil::assureIsArray(
            $this->postGroupActions[self::DIM][$dimensionsnr],
            self::OBJEKT
        );
        $this->postGroupActions[self::DIM][$dimensionsnr][self::OBJEKT][$objektnr] =
            $objeknamn;
    }

    /**
     * Manage 'Saldoposter' balanser och budget
     *
     * @param string $label
     * @param string[] $rowData
     * @return void
     * @throws RuntimeException
     */
    private function readSummaryData( string $label, array $rowData ) : void
    {
        switch( $label ) {
            /**
             *        Ing??ende balans f??r balanskonto
             *
             * #IB ??rsnr konto saldo kvantitet(opt)
             * valfri
             */
            case self::IB :
                $this->sie4Dto->addIbDto( self::getBalansDto( $rowData ));
                break;

            /**
             * Utg??ende balans f??r balanskonto
             *
             * #UB ??rsnr konto saldo kvantitet(opt)
             * valfri
             */
            case self::UB :
                $this->sie4Dto->addUbDto( self::getBalansDto( $rowData ));
                break;

            /**
             * Ing??ende balans f??r balanskonto (med objekt)
             *
             * #OIB ??rsnr konto {dimensionsnr objektnr} saldo kvantitet(opt)
             * valfri
             */
            case self::OIB :
                $this->sie4Dto->addOibDto( self::getBalansObjektDto( $rowData ));
                break;

            /**
             * Utg??ende balans f??r balanskonto (med objekt)
             *
             * #OUB ??rsnr konto {dimensionsnr objektnr} saldo kvantitet(opt)
             * valfri
             */
            case self::OUB :
                $this->sie4Dto->addOubDto( self::getBalansObjektDto( $rowData ));
                break;

            /**
             * Saldo f??r resultatkonto
             *
             * #RES ??rs konto saldo kvantitet
             * valfri
             */
            case self::RES :
                $this->sie4Dto->addSaldoDto( self::getBalansDto( $rowData ));
                break;

            /**
             * Periodsaldopost
             *
             * #PSALDO ??rsnr period konto {dimensionsnr objektnr} saldo kvantitet(opt)
             * valfri
             */
            case self::PSALDO :
                $this->sie4Dto->addPsaldoDto( self::getPeriodDto( $rowData ));
                break;

            /**
             * Periodbudgetpost
             *
             * #PBUDGET ??rsnr period konto {dimensionsnr objektnr} saldo kvantitet(opt)
             * valfri
             */
            case self::PBUDGET :
                $this->sie4Dto->addPbudgetDto( self::getPeriodDto( $rowData ));
                break;
        } // end switch
    }

    /**
     * Return BalansDto, #IB/#UB/#RES from rowData (??rsnr konto saldo kvantitet(opt))
     *
     * @param string[] $rowData
     * @return BalansDto
     */
    private static function getBalansDto( array $rowData ) : BalansDto
    {
        ArrayUtil::assureArrayLength( $rowData, 4 );
        $balansDto = new BalansDto();
        $balansDto->setArsnr( $rowData[0] );
        $balansDto->setKontoNr( $rowData[1] );
        $balansDto->setSaldo( $rowData[2] );
        if( null !== $rowData[3] ) {
            $balansDto->setKvantitet( $rowData[3] );
        }
        return $balansDto;
    }

    /**
     * Return BalansObjektDto, #OIB/#OUB from rowData (??rsnr konto {dimensionsnr objektnr} saldo kvantitet(opt))
     *
     * @param string[] $rowData
     * @return BalansObjektDto
     */
    private static function getBalansObjektDto( array $rowData ) : BalansObjektDto
    {
        ArrayUtil::assureArrayLength( $rowData, 5 );
        $balansObjektDto = new BalansObjektDto();
        $balansObjektDto->setArsnr( $rowData[0] );
        $balansObjektDto->setKontoNr( $rowData[1] );
        $balansObjektDto->setSaldo( $rowData[3] );
        [ $dimensionNr, $objektNr ] = explode( StringUtil::$SP1, $rowData[2], 2 );
        $balansObjektDto->setDimensionNr( $dimensionNr );
        $balansObjektDto->setObjektNr( $objektNr );
        if( null !== $rowData[4] ) {
            $balansObjektDto->setKvantitet( $rowData[4] );
        }
        return $balansObjektDto;
    }

    /**
     * Return PeriodDto from rowData (??rsnr period konto {dimensionsnr objektnr} saldo kvantitet(opt))
     *
     * PSALDO/PBUDGET
     *
     * @param string[] $rowData
     * @return PeriodDto
     */
    private static function getPeriodDto( array $rowData ) : PeriodDto
    {
        ArrayUtil::assureArrayLength( $rowData, 6 );
        $periodDto = new PeriodDto();
        $periodDto->setArsnr( $rowData[0] );
        $periodDto->setKontoNr( $rowData[2] );
        $periodDto->setSaldo( $rowData[4] );
        $periodDto->setPeriod( $rowData[1] );
        if( ! empty( $rowData[3] )) {
            [ $dimensionNr, $objektNr ] = explode( StringUtil::$SP1, $rowData[3], 2 );
            $periodDto->setDimensionNr( $dimensionNr );
            $periodDto->setObjektNr( $objektNr );
        }
        if( null !== $rowData[5] ) {
            $periodDto->setKvantitet( $rowData[5] );
        }
        return $periodDto;
    }

    /**
     * Manage 'Verifikationsposter'
     *
     * Note f??r VER
     * if verdatum is missing, date 'now' is used
     * if regdatum is missing, verdatum is used
     * if sign is missing, GEN _sign_ is used
     *
     * @param string $label
     * @param string[] $rowData
     * @return void
     * @throws RuntimeException
     */
    private function readVerTransData( string $label, array $rowData ) : void
    {
        if( in_array( $rowData[0], StringUtil::$CURLYBRACKETS )) {
            return;
        }
        switch( $label ) {
            case self::VER :
                $this->readVerData( $rowData );
                break;

            /**
             * Transaktionspost (inom Verifikationspost)
             *
             * #TRANS kontonr {objektlista} belopp transdat(opt) transtext(opt) kvantitet(opt) sign(opt)
             * valfri
             */
            case self::TRANS :
                $this->readTransData( $rowData, self::TRANS );
                break;

            /**
             * Tillagd transaktionspost (inom Verifikationspost)
             *
             * #RTRANS kontonr {objektlista} belopp transdat(opt) transtext(opt) kvantitet(opt) sign(opt)
             * valfri
             */
            case self::RTRANS :
                $this->readTransData( $rowData, self::RTRANS );
                break;

            /**
             * Borttagen transaktionspost (inom Verifikationspost)
             *
             * #BTRANS kontonr {objektlista} belopp transdat(opt) transtext(opt) kvantitet(opt) sign(opt)
             * valfri
             */
            case self::BTRANS :
                $this->readTransData( $rowData, self::BTRANS );
                break;
        } // end switch
    }

    /**
     * Manage #VER data
     *
     * #VER serie vernr verdatum vertext regdatum sign
     *
     * @param string[] $rowData
     * @return void
     */
    private function readVerData( array $rowData ) : void
    {
        ArrayUtil::assureArrayLength( $rowData, 6 );
        [ $serie, $vernr, $verdatum, $vertext, $regdatum, $sign ] = $rowData;
        // save for later #TRANS use
        $this->currentVerDto = new VerDto();
        $this->sie4Dto->addVerDto( $this->currentVerDto );

        if( ! empty( $serie )) {
            $this->currentVerDto->setSerie( $serie );
        }
        if( ! empty( $vernr )) {
            $this->currentVerDto->setVernr((int) $vernr );
        }
        $this->currentVerDto->setVerdatum(
            DateTimeUtil::getDateTime( $verdatum, self::VER, 1711 )
        );
        if( ! empty( $vertext )) {
            $this->currentVerDto->setVertext( $vertext );
        }
        // set to verdatum if missing, skipped in Sie4Iwriter2 if equal
        $this->currentVerDto->setRegdatum(
            empty( $regdatum )
                ? $this->currentVerDto->getVerdatum()
                : DateTimeUtil::getDateTime( $regdatum, self::VER, 1712 )
        );
        if( ! empty( $sign )) {
            $this->currentVerDto->setSign( $sign );
        }
    }

    /**
     * Manage #TRANS data
     *
     * #TRANS kontonr {objektlista} belopp transdat(opt) transtext(opt) kvantitet sign
     *
     * @param string[] $rowData
     * @param string $label
     * @return void
     */
    private function readTransData( array $rowData, string $label ) : void
    {
        ArrayUtil::assureArrayLength( $rowData, 7 );
        [
            $kontonr,
            $objektlista,
            $belopp,
            $transdat,
            $transtext,
            $kvantitet,
            $sign
        ] = $rowData;

        $transDto = new TransDto();
        $transDto->setTransType( $label );
        $transDto->setKontoNr( $kontonr );
        self::updObjektlista( $transDto, $objektlista );
        $transDto->setBelopp( $belopp );
        if( ! empty( $transdat )) {
            $transDto->setTransdat(
                DateTimeUtil::getDateTime( $transdat, $label, 1713 )
            );
        } // end if
        if( ! empty( $transtext )) {
            $transDto->setTranstext( $transtext );
        }
        if( null !== $kvantitet ) {
            $transDto->setKvantitet( $kvantitet );
        }
        if( ! empty( $sign )) {
            $transDto->setSign( $sign );
        }
        $this->currentVerDto->addTransDto( $transDto );
    }

    /**
     * Create DimObjektDtos from objektlista, i.e. pairs of dimId/objectId
     *
     * DimensionNr may contain underdimension, i.e. 'hierarkiska dimensioner'
     *
     * @param TransDto $transDto
     * @param string   $objektlista
     * @return void
     */
    private static function updObjektlista( TransDto $transDto, string $objektlista ) : void
    {
        if( empty( $objektlista )) {
            return;
        } // end if
        $dimObjList = explode( StringUtil::$SP1, trim( $objektlista ));
        $len        = count( $dimObjList ) - 1;
        for( $x1 = 0; $x1 < $len; $x1 += 2 ) {
            $x2     = $x1 + 1;
            $transDto->addDimIdObjektId(
                (int) $dimObjList[$x1],
                $dimObjList[$x2]
            );
        } // end for
    }

    /**
     * Due to labels in group are NOT required to be in order, aggregate or opt fix read missing parts here
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @return void
     */
    private function postReadGroupAction() : void
    {
        if( empty( $this->postGroupActions )) {
            return;
        }
        foreach($this->postGroupActions as $groupActionKey => $values ) {
            switch( $groupActionKey ) {
                case self::DIM :
                    $this->postDimActions( $values );
                    break;

                case self::KONTO :
                    $this->postKontoActions( $values );
                    break;
            } // end switch
        } // end foreach
        $this->postGroupActions = [];
    }

    /**
     * Create DimDto/DimObjektDto for all DIM/OBJECT
     *
     * @param array $dimValues
     * @return void
     */
    private function postDimActions( array $dimValues ) : void
    {
        // $dimensionData[0] = namn
        // $dimensionData[self::UNDERDIM][underDimNr] = underDimNamn
        // $dimensionData[self::OBJEKT][objektnr]     = objektnamn
        foreach(  $dimValues as $dimensionId => $dimensionData ) {
            if( isset( $dimensionData[0] )) {
                // #DIM namn
                $this->sie4Dto->addDim(
                    $dimensionId,
                    $dimensionData[0]
                );
            }
            if( isset( $dimensionData[self::UNDERDIM] )) {
                // #UNDERDIM
                foreach( $dimensionData[self::UNDERDIM] as $underDimNr => $underDimNamn ) {
                    $this->sie4Dto->addUnderDim(
                        $underDimNr,
                        $underDimNamn,
                        $dimensionId
                    );
                }
            }
            if( isset( $dimensionData[self::OBJEKT] )) {
                // #OBJEKT
                foreach( $dimensionData[self::OBJEKT] as $objektNr => $objektNamn ) {
                    $this->sie4Dto->addDimObjekt(
                        $dimensionId,
                        (string) $objektNr,
                        $objektNamn
                    );
                } // end foreach
            } // end if
        } // end foreach
    }

    /**
     * Create AccountDto for all KONTO/KTYP/ENHET
     *
     * @param string[] $kontoValues
     * @return void
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    private function postKontoActions( array $kontoValues ) : void
    {
        // kontoNr[0] = kontoNamn
        // kontoNr[1] = kontoTyp, Ska vara n??gon av typerna T, S, K, I
        // kontoNr[2] = enhet
        ksort( $kontoValues );
        foreach(  $kontoValues as $kontoNr => $kontoData ) {
            $this->sie4Dto->addAccount(
                (string) $kontoNr,
                $kontoData[0],
                $kontoData[1],
                ( $kontoData[2] ?? null )
            );
        } // end foreach
    }
}
