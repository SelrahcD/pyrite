<?php

namespace Pyrite\Response;


class ResponseBagImpl implements ResponseBag
{
    protected $data = array();
    protected $errors = array();
    protected $result = "";
    protected $resultCode = 200;

    public function set($key, $value)
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function get($key, $defaultValue = null)
    {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }
        return $defaultValue;
    }

    public function has($key)
    {
        return array_key_exists($key, $this->data);
    }

    public function setResult($value)
    {
        $this->result = $value;
        return $this;
    }

    public function getResult()
    {
        return $this->result;
    }

    public function setResultCode($value)
    {
        $this->resultCode = $value;
        return $this;
    }

    public function getResultCode()
    {
        return $this->resultCode;
    }
}