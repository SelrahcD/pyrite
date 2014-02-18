<?php

namespace Pyrite\Kernel;

use DICIT\Config\YML;
use DICIT\Config\PHP;
use DICIT\Container;

use Pyrite\Config\NullConfig;
use Pyrite\Kernel\Exception\HttpException;
use Pyrite\StackDispatched;

use Stack\StackedHttpKernel;

use Symfony\Component\Debug\Debug;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Pyrite\Stack\Template;

/**
 * PyriteKernel
 * 
 * Main kernel for a pyrite application
 */
class PyriteKernel implements HttpKernelInterface, TerminableInterface
{
    /**
     * Route collection
     * 
     * @var RouteCollection
     */
    private $routeCollection;
    
    /**
     * Debug mode (default is false)
     * 
     * @var debug
     */
    private $debug = false;
    
    /**
     * DIC
     * 
     * @var Container
     */
    private $container;
    
    /**
     * Stacked kernel
     * 
     * @var \Pyrite\Stack\Template
     */
    private $stack = null;
    
    public function __construct($routingPath, $containerPath = null, $debug = false)
    {
        Debug::enable(null, $debug);
        
        $config = new NullConfig();
        
        if (null !== $containerPath && preg_match('/.*yml$/', $containerPath)) {
            $config = new YML($containerPath);
        }
        
        if (null !== $containerPath && preg_match('/.*php$/', $containerPath)) {
            $config = new PHP($containerPath);
        }
        
        $this->container       = new Container($config);
        $this->debug           = $debug;
        $this->routeCollection = $this->buildRouteCollection($routingPath);
    }

    /**
     * {@inheritDoc}
     */
    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true) {
        $context  = new RequestContext();
        $context->fromRequest($request);
        
        $urlMatcher   = new UrlMatcher($this->routeCollection, $context);
        $urlGenerator = new UrlGenerator($this->routeCollection, $context);
        
        try {
            $parameters = $this->urlMatcher->match($request->getPathInfo());
        } catch (ResourceNotFoundException $e) {
            throw new HttpException(Response::HTTP_NOT_FOUND, null, $e);
        } catch (MethodNotAllowedException $e) {
            throw new HttpException(Response::HTTP_METHOD_NOT_ALLOWED);
        }

        $this->stack = $this->getStack($parameters);
        
        return $this->stack->handle($request, $type, $catch);
    }

    /**
     * {@inheritDoc}
     */
    public function terminate(Request $request, Response $response)
    {
        if (null !== $this->stack) {
            $this->stack->terminate($request, $response);
        }
    }
    
    /**
     * Load kernel, handle a request from a webserver and send the response
     * 
     * Utility function for the entrypoint of your application, only use when you are in a request context (from a webserver)
     */
    public static function boot($routingPath, $containerPath = null, $debug = false) {
        try {
            $kernel = new self();
            
            $request = Request::createFromGlobals();
            $response = $kernel->handle($request, HttpKernelInterface::MASTER_REQUEST, true);
        } catch (HttpException $exception) {
            $response = new Response($exception->getMessage(), $exception->getCode());
        } catch (Exception $exception) {
            $response = new Response($exception->getMessage(), $exception->getCode() === 0 ? Response::HTTP_INTERNAL_SERVER_ERROR : $exception->getCode());
        }
        
        $response->send();
        
        if ($kernel instanceof TerminableInterface) {
            $kernel->terminate($request, $response);
        }
    }
    
    /**
     * Build a stack for a specific route
     * 
     * Using template stack for better reusability
     * 
     * @param array $routeParameters Parameters of the route
     * 
     * @return \SplStack
     */
    protected function getStack($routeParameters)
    {
        $stack = new Template(array_keys($routeParameters['dispach']), $this->container);
        $stack->setParameters($routeParameters['dispach']);
        
        return $stack;
    }
    
    /**
     * Build a route collection from a config file
     * 
     * @param string $routingPath Path to routing
     * 
     * @return RouteCollection A collection of routes
     */
    protected function buildRouteCollection($routingPath)
    {
        //@TODO Caching
        $config = new YML($routingPath);
        $configuration = $config->load();
        
        //@TODO Validation ?
        
        //Build route collection
        $routes = new RouteCollection();
        
        foreach ($configuration['routes'] as $name => $routeParameters) {
            $route = new Route($routeParameters['route']['pattern'], array(), array(), $routeParameters, '', array(), $routeParameters['route']['methods']);
            $routes->add($name, $route);
        }
        
        return $routes;
    }
}