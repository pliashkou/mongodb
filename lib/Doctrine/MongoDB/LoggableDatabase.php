<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\MongoDB;

use Doctrine\Common\EventManager;
use MongoDB\Database;

/**
 * Wrapper for the MongoDB class with logging functionality.
 *
 * @since  1.0
 * @author Jonathan H. Wage <jonwage@gmail.com>
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 */
class LoggableDatabase extends Database implements Loggable
{
    /**
     * The logger callable.
     *
     * @var callable
     */
    protected $loggerCallable;

    /**
     * Constructor.
     *
     * @param Connection   $connection     Connection used to create Collections
     * @param \MongoDB     $mongoDB        MongoDB instance being wrapped
     * @param EventManager $evm            EventManager instance
     * @param integer      $numRetries     Number of times to retry queries
     * @param callable     $loggerCallable The logger callable
     */
    public function __construct(Connection $connection, $mongoDB, EventManager $evm, $numRetries, $loggerCallable)
    {
        if ( ! is_callable($loggerCallable)) {
            throw new \InvalidArgumentException('$loggerCallable must be a valid callback');
        }
        parent::__construct($evm, $mongoDB->getDatabaseName());
        $this->loggerCallable = $loggerCallable;
    }

    /**
     * Log something using the configured logger callable.
     *
     * @see Loggable::log()
     * @param array $log
     */
    public function log(array $log)
    {
        $log['db'] = $this->getName();
        call_user_func_array($this->loggerCallable, array($log));
    }

    /**
     * @see Database::authenticate()
     */
    public function authenticate($username, $password)
    {
        $this->log(array(
            'authenticate' => true,
            'username' => $username,
            'password' => $password,
        ));

        return parent::authenticate($username, $password);
    }

    /**
     * @see Database::command()
     */
    public function command($command, array $options = [])
    {
        $this->log(array(
            'command' => true,
            'data' => $command,
            'options' => $options,
        ));

        return parent::command($command, $options);
    }

    /**
     * @see Database::createCollection()
     */
    public function createCollection($collectionName, array $options = [])
    {
        $options = array_merge(array('capped' => false, 'size' => 0, 'max' => 0), $options);

        $this->log(array(
            'createCollection' => true,
            'name' => $collectionName,
            'options' => $options,
            /* @deprecated 1.1 Replaced by options; will be removed for 2.0 */
            'capped' => $options['capped'],
            'size' => $options['size'],
            'max' => $options['max'],
        ));

        return parent::createCollection($collectionName, $options);
    }

    /**
     * @see Database::drop()
     */
    public function drop(array $options = [])
    {
        $this->log(array('dropDatabase' => true, 'options'=> $options));

        return parent::drop($options);
    }

    /**
     * @see Database::execute()
     */
    public function execute($code, array $args = array())
    {
        $this->log(array(
            'execute' => true,
            'code' => $code,
            'args' => $args,
        ));

        return parent::execute($code, $args);
    }

    /**
     * @see Database::getDBRef()
     */
    public function getDBRef(array $ref)
    {
        $this->log(array(
            'getDBRef' => true,
            'reference' => $ref,
        ));

        return parent::getDBRef($ref);
    }

    /**
     * Return a new LoggableCollection instance.
     *
     * @see Database::doSelectCollection()
     * @param string $name
     * @return LoggableCollection
     */
    protected function doSelectCollection($name)
    {
        $mongoCollection = $this->mongoDB->selectCollection($name);

        return new LoggableCollection($this, $mongoCollection, $this->eventManager, $this->numRetries, $this->loggerCallable);
    }
}
