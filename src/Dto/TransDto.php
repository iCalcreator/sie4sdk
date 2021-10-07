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
namespace Kigkonsult\Sie4Sdk\Dto;

use DateTime;
use InvalidArgumentException;
use Kigkonsult\Sie4Sdk\Dto\Traits\FnrIdOrgnr2Trait;
use Kigkonsult\Sie4Sdk\Dto\Traits\KontoNrTrait;
use Kigkonsult\Sie4Sdk\Dto\Traits\KvantitetTrait;
use Kigkonsult\Sie4Sdk\Dto\Traits\SerieVernrTrait;
use Kigkonsult\Sie4Sdk\Dto\Traits\SignTrait;

use Kigkonsult\Sie4Sdk\Util\Assert;
use function in_array;
use function count;
use function sprintf;

/**
 * Class TransDto
 *
 * Inherit timestamp, guid, fnrId and orgnr(+multiple) properties from BaseId,
 * to uniquely identify instance
 * The properties and serie and vernr are populated down from 'parent' verDto
 * trandsdat also (from verdtum), s missing
 *
 * kontonr and belopp required,
 *   in objektlista (if set), pairs of dimension and objektnr required
 */
class TransDto extends BaseId implements KontoNrInterface
{
    /**
     * @var string[]
     */
    private static $allowedTypes = [ self::TRANS, self::RTRANS, self::BTRANS ];

    /**
     * Serie and vernr
     */
    use SerieVernrTrait;

    /**
     * @var string  one of allowedTypes
     */
    private $transType = self::TRANS;

    use KontoNrTrait;

    /**
     * @var DimObjektDto[]
     */
    private $objektlista = [];

    /**
     * @var float
     */
    private $belopp = null;

    /**
     * @var DateTime
     */
    private $transdat = null;

    /**
     * @var string
     */
    private $transtext = null;

    use KvantitetTrait;

    use SignTrait;

    use FnrIdOrgnr2Trait;

    /**
     * Class factory method, kontoNr/belopp
     *
     * @param int|string $kontoNr
     * @param float      $belopp
     * @param string     $transType
     * @return self
     */
    public static function factory( $kontoNr, float $belopp, $transType = self::TRANS ) : self
    {
        $instance = new self();
        $instance->setKontoNr( $kontoNr );
        $instance->setBelopp( $belopp );
        $instance->setTransType( $transType );
        return $instance;
    }

    /**
     * @return string #TRANS/#RTRANS/#BTRANS
     */
    public function getTransType() : string
    {
        return $this->transType;
    }

    /**
     * @param string $transType
     * @return self
     */
    public function setTransType( string $transType ) : self
    {
        static $FMT = 'Fel trans-typ %s, #TRANS/#RTRANS/#BTRANS förväntas';
        if( ! in_array( $transType, self::$allowedTypes )) {
            throw new InvalidArgumentException(
                sprintf( $FMT, $transType )
            );
        }
        $this->transType = $transType;
        return $this;
    }

    /**
     * Set serie
     *
     * @param int|string $serie
     * @return self
     */
    public function setSerie( $serie ) : self
    {
        Assert::isIntOrString( self::VERSERIE, $serie );
        $this->serie = (string) $serie;
        return $this;
    }


    /**
     * Set vernr
     *
     * @param int $vernr
     * @return self
     */
    public function setVernr( int $vernr ) : self
    {
        $this->vernr = $vernr;
        return $this;
    }

    /**
     * Return int count DimObjektDtos (pairs of dim/objekt) in objektlista
     *
     * @return int
     */
    public function countObjektlista() : int
    {
        return count( $this->objektlista );
    }

    /**
     * Return objektlista, array DimObjektDto[], (pairs of dim/objekt)
     *
     * @return DimObjektDto[]
     */
    public function getObjektlista() : array
    {
        return $this->objektlista;
    }

    /**
     * Add objektlista element, dimId, objektId
     *
     * @param int $dimId
     * @param string $objektId
     * @return self
     */
    public function addDimIdObjektId( int $dimId, string $objektId ) : self
    {
        return $this->addObjektlista(
            DimObjektDto::factoryDimObject( $dimId, $objektId )
        );
    }

    /**
     * Add objektlista element, DimObjektDto, (pair of dim/objekt)
     *
     * @param DimObjektDto $dimObjektDto
     * @return self
     */
    public function addObjektlista( DimObjektDto $dimObjektDto ) : self
    {
        $this->objektlista[] = $dimObjektDto;
        return $this;
    }

    /**
     * Set objektlista, array DimObjektDto[], (pairs of dim/objekt)
     *
     * @param DimObjektDto[] $dimObjektDtos
     * @return self
     */
    public function setObjektlista( array $dimObjektDtos ) : self
    {
        $this->objektlista = [];
        foreach( $dimObjektDtos as $dimObjekt ) {
            $this->addObjektlista( $dimObjekt );
        }
        return $this;
    }

    /**
     * Return belopp
     *
     * @return float
     */
    public function getBelopp()
    {
        return $this->belopp;
    }

    /**
     * Return bool true if belopp is set
     *
     * @return bool
     */
    public function isBeloppSet() : bool
    {
        return ( null !== $this->belopp );
    }

    /**
     * Set belopp
     *
     * @param int|float|string $belopp
     * @return self
     */
    public function setBelopp( $belopp ) : self
    {
        $this->belopp = (float) $belopp;
        return $this;
    }

    /**
     * Return transdat
     *
     * @return DateTime
     */
    public function getTransdat()
    {
        return $this->transdat;
    }

    /**
     * Return bool true if transdat is set
     *
     * @return bool
     */
    public function isTransdatSet() : bool
    {
        return ( null !== $this->transdat );
    }

    /**
     * Set transdat
     *
     * @param DateTime $transdat
     * @return self
     */
    public function setTransdat( DateTime $transdat ) : self
    {
        $this->transdat = $transdat;
        return $this;
    }

    /**
     * Return transtext
     *
     * @return string
     */
    public function getTranstext()
    {
        return $this->transtext;
    }

    /**
     * Return bool true if transtext is set
     *
     * @return bool
     */
    public function isTranstextSet() : bool
    {
        return ( null !== $this->transtext );
    }

    /**
     * Set transtext
     *
     * @param string $transtext
     * @return self
     */
    public function setTranstext( string $transtext ) : self
    {
        $this->transtext = $transtext;
        return $this;
    }
}
