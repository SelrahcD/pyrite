<?php

namespace Pyrite\Response;


interface ResponseBag
{
    function set($key, $value);
    function get($key, $defaultValue = null);
    function has($key);

    function setResult($value);
    function getResult();

    function setResultCode($value);
    function getResultCode();
}
