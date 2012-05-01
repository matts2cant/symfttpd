<?php
/**
 * This file is part of the Symfttpd Project
 *
 * (c) Laurent Bachelier <laurent@bachelier.name>
 * (c) Benjamin Grandfond <benjamin.grandfond@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfttpd\Tail;

/**
 * TailInterface interface
 *
 * @author Benjamin Grandfond <benjaming@theodo.fr>
 */
interface TailInterface
{
    public function consume();
}
