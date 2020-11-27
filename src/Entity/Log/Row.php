<?php


namespace App\Entity\Log;


final class Row
{
    private string $remoteHost;

    private string $rfc931;

    private string $authUser;

    private int $timestamp;

    private string $request;

    private int $status;

    private int $bytes;

    public function __construct(string $remoteHost, string $rfc931, string $authUser, int $timestamp, string $request, int $status, int $bytes)
    {
        $this->remoteHost = $remoteHost;
        $this->rfc931 = $rfc931;
        $this->authUser = $authUser;
        $this->timestamp = $timestamp;
        $this->request = $request;
        $this->status = $status;
        $this->bytes = $bytes;
    }

    public function getId()
    {
        return sprintf(
            "%s-%s-%s-%d-%s-%d-%d",
            $this->getRemoteHost(),
            $this->getRfc931(),
            $this->getAuthUser(),
            $this->getTimestamp(),
            $this->getRequest(),
            $this->getStatus(),
            $this->getBytes()
        );
    }

    public function getRemoteHost(): string
    {
        return $this->remoteHost;
    }

    public function getRfc931(): string
    {
        return $this->rfc931;
    }

    public function getAuthUser(): string
    {
        return $this->authUser;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function getRequest(): string
    {
        return $this->request;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getBytes(): int
    {
        return $this->bytes;
    }
}
