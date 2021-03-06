<?php

namespace Keboola\GenericExtractor;

use Keboola\GenericExtractor\Exception\UserException;
use Keboola\Temp\Temp;
use Symfony\Component\Process\Process;

class SSH
{
    const SSH_SERVER_ALIVE_INTERVAL = 15;

    /**
     * @var Temp
     */
    private $temp;

    public function __construct()
    {
        $this->temp = new Temp('ssh-tunnel');
    }

    /**
     *
     * $user, $sshHost, $localPort, $remoteHost, $remotePort, $privateKey, $sshPort = '22'
     *
     * @param array $config
     *  - user
     *  - sshHost
     *  - sshPort
     *  - localPort
     *  - remoteHost
     *  - remotePort
     *  - privateKey
     *
     * @throws UserException
     */
    public function openTunnel(array $config)
    {
        $missingParams = array_diff(
            ['user', 'sshHost', 'sshPort', 'localPort','privateKey'],
            array_keys($config)
        );

        if (!empty($missingParams)) {
            throw new UserException(sprintf("Missing parameters '%s'", implode(',', $missingParams)));
        }

        $cmd = sprintf(
            'ssh -D %s %s@%s -p %s -i %s -fN -o ExitOnForwardFailure=yes -o StrictHostKeyChecking=no -o ServerAliveInterval=%d',
            $config['localPort'],
            $config['user'],
            $config['sshHost'],
            $config['sshPort'],
            $this->writeKeyToFile($config['privateKey']),
            self::SSH_SERVER_ALIVE_INTERVAL
        );

        $process = new Process($cmd);
        $process->setTimeout(60);
        $process->start();

        while ($process->isRunning()) {
            sleep(1);
        }

        if ($process->getExitCode() !== 0) {
            throw new UserException(sprintf(
                "Unable to create ssh tunnel. Output: %s ErrorOutput: %s",
                $process->getOutput(),
                $process->getErrorOutput()
            ));
        }
    }

    /**
     * @param string $key
     * @return string
     * @throws UserException
     */
    private function writeKeyToFile($key)
    {
        if (empty($key)) {
            throw new UserException("Key must not be empty");
        }
        $path = $this->temp->createFile('ssh-key')->getRealPath();
        file_put_contents($path, $key);
        chmod($path, 0600);
        return $path;
    }
}
