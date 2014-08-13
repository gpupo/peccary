<?php

require_once __DIR__.'/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Gpupo\Petfinder\Search\Search;
use Gpupo\Petfinder\Search\Query\Filters;
use Gpupo\Petfinder\Search\Query\Query;
use Gpupo\Petfinder\Search\Query\Keywords;
use Gpupo\Petfinder\Search\Paginator\Paginator;
use Cocur\Slugify\Bridge\Twig\SlugifyExtension;
use Cocur\Slugify\Slugify;

$app = new Silex\Application;

$app['debug'] = true;

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Cocur\Slugify\Bridge\Silex\SlugifyServiceProvider());

if (file_exists(__DIR__.'/../config/config.php')) {
    $configFile = __DIR__.'/../config/config.php';
} else {
    $configFile = __DIR__.'/../config/config.dist.php';
}
$config = include($configFile);

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views/' . $config['template'],
));

$app['twig'] = $app->share($app->extend('twig', function ($twig, $app) {
    $twig->addFilter('buildUrlQuery', new \Twig_SimpleFilter('buildUrlQuery', function ($routePars) {
        $url = http_build_query($routePars);

        return $url;
    }, array('is_safe' => array('html'))));

    $twig->addExtension(new SlugifyExtension(Slugify::create()));

    return $twig;
}));

$search = new Search;
$search->getSphinxService()->setParameters($config['sphinx']);

$app->get('/', function () use ($search, $app) {
    return $app['twig']->render('home.html.twig', array());
});

$lambda = function (Request $request, $field = null, $value = null) use ($search, $app) {

    if (empty($value)) {
        $field = 'search';
        $value = $request->query->get('value');
    }

    if (empty($value)) {
        throw new \Exception('Page Not Found');
    }

    //  $search->getSphinxClient()->setMatchModeByModeName($request->query->get('mode', 'extended'));

    $filters = new Filters;

    $keywords = new Keywords;
    if ($field != 'search') {
        $search->getSphinxClient()->setMatchModeByModeName($request->query->get('mode', 'extended'));
        $keywords->setKey($field);
        $keywords->setStrict(true);

        //$filters->appendValueFilter($field, $value);
    }

    $keywords->readString($value);

    $query = new Query($keywords);
    $query->setIndex('main');
    $query->setFilters($filters);

    //Pagination
    $page = intval($request->query->get('page', 1));
    $paginator = new Paginator;
    $paginator->paginate(null, $page, (16));
    $paginator->setPageRange(15);
    $query->setPaginator($paginator);

    $results = $search->findByQuery($query);

    if ($field == 'sku') {
        return $app['twig']->render('description.html.twig', array(
            'item'   => $results->getFirst()
        ));
    }

    $pars = $request->query->all();
    unset($pars['page']);
    unset($pars['value']);
    $route =  array(
        'path' => '/' . $field . '/' .  $app['slugify']->slugify($value) . '/',
        'pars' => $pars,
    );

    return $app['twig']->render('result.html.twig', array(
        'field'     => $field,
        'word'      => $value,
        'results'   => $results,
        'route'     => $route,
    ));
};

$app->get('/search/', $lambda);
$app->get('/{field}/{value}/', $lambda)->bind('fieldRoute')->value('value', '');

$app->get('/sitemap/', function (Request $request) use ($search, $app) {

    $search->getSphinxClient()->setMatchModeByModeName('fullscan');
    $keywords = new Keywords;
    $keywords->setData('availability', array('stock'));
    $query = new Query($keywords);
    $query->setIndex('main');
    $query->setLimit(9900);
    $results = $search->findByQuery($query);

    $absolutePath = $request->getScheme() . '://' . $request->getHttpHost() . $request->getBasePath();

    $content = $app['twig']->render('sitemap.xml.twig', array(
        'absolutePath'  => $absolutePath,
        'results'       => $results,
    ));

    return new Response($content,200, array('Content-Type' => 'application/xml'));
});

$app->error(function (\Exception $e, $code) use ($app) {
    return $app['twig']->render('error.html.twig', array('exception' => $e));
});

$app->run();
