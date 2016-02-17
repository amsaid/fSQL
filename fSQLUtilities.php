<?php

/* A reentrant read write lock for a file */
class fSQLFile
{
    protected $handle;
    private $filepath;
    private $lock;
    private $rcount = 0;
    private $wcount = 0;

    public function __construct($filepath)
    {
        $this->filepath = $filepath;
        $this->handle = null;
        $this->lock = 0;
    }

    public function __destruct()
    {
        // should be unlocked before reaches here, but just in case,
        // release all locks and close file
        if(isset($this->handle)) {
            // flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }
    }

    public function exists()
    {
        return file_exists($this->filepath);
    }

    public function drop()
    {
        // only allow drops if not locked
        if($this->handle === null)
        {
            unlink($this->filepath);
            return true;
        }
        else
            return false;
    }

    public function getHandle()
    {
        return $this->handle;
    }

    public function getPath()
    {
        return $this->filepath;
    }

    public function acquireRead()
    {
        if($this->lock !== 0 && $this->handle !== null) {  /* Already have at least a read lock */
            $this->rcount++;
            return true;
        }
        else if($this->lock === 0 && $this->handle === null) /* New lock */
        {
            $this->handle = fopen($this->filepath, 'rb');
            if($this->handle)
            {
                flock($this->handle, LOCK_SH);
                $this->lock = 1;
                $this->rcount = 1;
                return true;
            }
        }

        return false;
    }

    public function acquireWrite()
    {
        if($this->lock === 2 && $this->handle !== null)  /* Already have a write lock */
        {
            $this->wcount++;
            return true;
        }
        else if($this->lock === 1 && $this->handle !== null)  /* Upgrade a lock*/
        {
            flock($this->handle, LOCK_EX);
            $this->lock = 2;
            $this->wcount++;
            return true;
        }
        else if($this->lock === 0 && $this->handle === null) /* New lock */
        {
            touch($this->filepath); // make sure it exists
            $this->handle = fopen($this->filepath, 'r+b');
            if($this->handle)
            {
                flock($this->handle, LOCK_EX);
                $this->lock = 2;
                $this->wcount = 1;
                return true;
            }
        }

        return false;
    }

    public function releaseRead()
    {
        if($this->lock !== 0 && $this->handle !== null)
        {
            $this->rcount--;

            if($this->lock === 1 && $this->rcount === 0) /* Read lock now empty */
            {
                // no readers or writers left, release lock
                flock($this->handle, LOCK_UN);
                fclose($this->handle);
                $this->handle = null;
                $this->lock = 0;
            }
        }

        return true;
    }

    public function releaseWrite()
    {
        if($this->lock !== 0 && $this->handle !== null)
        {
            if($this->lock === 2) /* Write lock */
            {
                $this->wcount--;
                if($this->wcount === 0) // no writers left.
                {
                    if($this->rcount > 0)  // only readers left.  downgrade lock.
                    {
                        flock($this->handle, LOCK_SH);
                        $this->lock = 1;
                    }
                    else // no readers or writers left, release lock
                    {
                        flock($this->handle, LOCK_UN);
                        fclose($this->handle);
                        $this->handle = null;
                        $this->lock = 0;
                    }
                }
            }
        }

        return true;
    }
}

class fSQLMicrotimeLockFile extends fSQLFile
{
    private $loadTime = null;
    private $lastReadStamp = null;

    public function __destruct()
    {
        parent::__destruct();
    }

    public function accept()
    {
        $this->loadTime = $this->lastReadStamp;
    }

    public function reset()
    {
        $this->loadTime = null;
        $this->lastReadStamp = null;
        return true;
    }

    public function wasModified()
    {
        $this->acquireRead();

        $this->lastReadStamp = fread($this->handle, 20);
        $modified = $this->loadTime === null || $this->loadTime < $this->lastReadStamp;

        $this->releaseRead();

        return $modified;
    }

    public function wasNotModified()
    {
        $this->acquireRead();

        $this->lastReadStamp = fread($this->handle, 20);
        $modified = $this->loadTime === null || $this->loadTime >= $this->lastReadStamp;

        $this->releaseRead();

        return $modified;
    }

    public function write()
    {
        $this->acquireWrite();

        list($msec, $sec) = explode(' ', microtime());
        $this->loadTime = $sec.$msec;
        ftruncate($this->handle, 0);
        fwrite($this->handle, $this->loadTime);

        $this->releaseWrite();
    }
}
