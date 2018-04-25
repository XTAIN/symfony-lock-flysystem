<?php

namespace XTAIN\Flysystem\Lock;

use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockExpiredException;
use Symfony\Component\Lock\Exception\LockStorageException;
use Symfony\Component\Lock\Exception\NotSupportedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\StoreInterface;

class FlysystemStore implements StoreInterface
{
    /**
     * @var FilesystemInterface
     */
    protected $filesystem;

    public function __construct(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function save(Key $key)
    {
        $this->lock($key);
    }

    public function waitAndSave(Key $key)
    {
        do {
            try {
                $this->lock($key);
                return;
            } catch (LockConflictedException $e) {
                // try again
                sleep(1);
                continue;
            }
        } while(true);
    }

    public function putOffExpiration(Key $key, $ttl)
    {
        $key->reduceLifetime($ttl);
        $lock = $this->read($key);
        $lock['expire'] = $ttl + microtime(true);
        $this->persist($key, $lock);
    }

    public function delete(Key $key)
    {
        try {
            if (!$this->filesystem->delete($this->filename($key))) {
                throw new LockStorageException('Unknown error ocurred during deleting lock', 0, null);
            }
        } catch (FileNotFoundException $e) {
            // do nothing
        }
    }

    public function exists(Key $key)
    {
        $lock = $this->read($key);

        if ($lock === null) {
            // if it not exists
            return false;
        }

        if (isset($lock['expire']) && $lock['expire'] <= microtime(true)) {
            // if it has expired
            return false;
        }

        return $lock['token'] === $this->getToken($key);
    }

    private function lock(Key $key)
    {
        // The lock is maybe already acquired.
        if ($this->exists($key)) {
            return;
        }

        $lock = $this->read($key);

        if ($lock !== null) {
            if (!isset($lock['expire']) || $lock['expire'] > microtime(true)) {
                // is still locked
                throw new LockConflictedException();
            }
        }

        if ($key->getRemainingLifetime() !== null) {
            $lock['expire'] = $key->getRemainingLifetime() + microtime(true);
        }

        $lock['token'] = $this->getToken($key);

        $this->persist($key, $lock);
    }

    /**
     * @param Key $key
     * @return array
     */
    protected function read(Key $key)
    {
        try {
            $lock = $this->filesystem->read($this->filename($key));

            if ($lock === false) {
                throw new LockStorageException('Unknown error ocurred during reading lock', 0, null);
            } else {
                return array_merge([
                    'token' => null
                ], json_decode($lock, true));
            }
        } catch (FileNotFoundException $e) {
            return null;
        }
    }

    /**
     * @param Key $key
     * @param array $lock
     */
    protected function persist(Key $key, array $lock)
    {
        if (!$this->filesystem->put(
            $this->filename($key),
            json_encode($lock)
        )) {
            $key->removeState(__CLASS__);
            throw new LockStorageException('Unknown error ocurred during writing lock', 0, null);
        }

        if ($key->isExpired()) {
            throw new LockExpiredException('Saving lock took to long time.');
        }
    }

    private function filename(Key $key)
    {
        return sprintf('sf.%s.%s.lock',
            preg_replace('/[^a-z0-9\._-]+/i', '-', $key),
            strtr(substr(base64_encode(hash('sha256', $key, true)), 0, 7), '/', '_')
        );
    }

    /**
     * Retrieves an unique token for the given key.
     *
     * @param Key $key
     *
     * @return string
     */
    private function getToken(Key $key)
    {
        if (!$key->hasState(__CLASS__)) {
            $token = base64_encode(random_bytes(32));
            $key->setState(__CLASS__, $token);
        }

        return $key->getState(__CLASS__);
    }
}