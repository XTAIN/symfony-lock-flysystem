<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XTAIN\Tests\Flysystem\Lock;

use XTAIN\Flysystem\Lock\FlysystemStore;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;
use Symfony\Component\Lock\Tests\Store\AbstractStoreTest;
use Symfony\Component\Lock\Tests\Store\ExpiringStoreTestTrait;

class FlysystemStoreTest extends AbstractStoreTest
{
    use ExpiringStoreTestTrait;

    /**
     * {@inheritdoc}
     */
    protected function getClockDelay()
    {
        return 250000;
    }

    /**
     * {@inheritdoc}
     */
    public function getStore()
    {
        return new FlysystemStore(new Filesystem(new Local(sys_get_temp_dir())));
    }
}

