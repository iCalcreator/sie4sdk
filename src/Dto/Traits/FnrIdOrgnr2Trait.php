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
namespace Kigkonsult\Sie4Sdk\Dto\Traits;

/**
 * Implements set-methods for properties fnrid, orgnr(+multiple), used in IdDto and TransDto
 */
trait FnrIdOrgnr2Trait
{
    /**
     * Set fnrId
     *
     * @param string $fnrId
     * @return self
     */
    public function setFnrId( string $fnrId ) : self
    {
        $this->fnrId = $fnrId;
        return $this;
    }

    /**
     * Set orgnr
     *
     * @param string $orgnr
     * @return self
     */
    public function setOrgnr( string $orgnr ) : self
    {
        $this->orgnr = $orgnr;
        return $this;
    }

    /**
     * Set multiple
     *
     * @param int $multiple
     * @return self
     */
    public function setMultiple( int $multiple ) : self
    {
        $this->multiple = $multiple;
        return $this;
    }
}
