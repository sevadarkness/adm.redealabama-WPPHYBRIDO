<?php
declare(strict_types=1);

use RedeAlabama\Support\PrometheusMetrics;


require_once __DIR__ . '/app/Support/PrometheusMetrics.php';


/**
 * Endpoint de métricas Prometheus.
 *
 * Exemplo de scrape:
 *   curl http://seu-dominio/adm.redealabama/metrics.php
 *
 * IMPORTANTE: em produção, proteger com firewall / basic auth / IP whitelisting.
 */

PrometheusMetrics::instance()->render();

