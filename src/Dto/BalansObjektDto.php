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
namespace Kigkonsult\Sie4Sdk\Dto;

use Kigkonsult\Sie4Sdk\Dto\Traits\DimensionNrTrait;
use Kigkonsult\Sie4Sdk\Dto\Traits\ObjektNrTrait;
use Kigkonsult\Sie4Sdk\Util\StringUtil;

/**
 * Class BalansObjektDto
 */
class BalansObjektDto extends BalansDto
{
    use DimensionNrTrait;

    use ObjektNrTrait;

    /**
     * @var callable
     */
    public static $SORTER = [ BalansObjektDto::class, 'balansObjektSorter' ];

    /**
     * Sort BalansObjektDto[] on kontonr, arsnr, dimensionNr, objektNr
     *
     * @param BalansObjektDto $a
     * @param BalansObjektDto $b
     * @return int
     */
    public static function balansObjektSorter( BalansObjektDto $a, BalansObjektDto $b ) : int
    {
        if( 0 !== ( $kontoNrArsnrCmp = parent::balansSorter( $a, $b ))) {
            return $kontoNrArsnrCmp;
        }
        if( 0 !== ( $res = StringUtil::strSort((string) $a->getDimensionNr(),(string) $b->getDimensionNr()))) {
            return $res;
        }
        return StringUtil::strSort((string) $a->getObjektNr(), (string)$b->getObjektNr());
    }
}
