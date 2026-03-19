<?php

declare(strict_types=1);

const INDODAX_BASE_URL = 'https://indodax.com/api/';
const DASHBOARD_VERSION = 'v3';
const DASHBOARD_USER_AGENT = 'clawbot-trading-v3-dashboard/1.0';
const DEFAULT_TRADING_DB = 'trading_db_v3';
const DEFAULT_INVESTMENT_HARDWARE_COST_IDR = 4500000.0;
const DEFAULT_INVESTMENT_ELECTRICITY_IDR_PER_KWH = 2000.0;
const DEFAULT_INVESTMENT_POWER_WATT = 27.0;
const DEFAULT_INVESTMENT_AMORTIZATION_DAYS = 365;

function loadEnvFile(string $path): void
{
		if (!is_file($path)) {
				return;
		}

		$lines = @file($path, FILE_IGNORE_NEW_LINES);
		if (!is_array($lines)) {
				return;
		}

		foreach ($lines as $line) {
				$line = trim((string) $line);
				if ($line === '' || str_starts_with($line, '#')) {
						continue;
				}

				$equalPos = strpos($line, '=');
				if ($equalPos === false || $equalPos === 0) {
						continue;
				}

				$key = trim(substr($line, 0, $equalPos));
				$value = trim(substr($line, $equalPos + 1));

				if ($key === '') {
						continue;
				}

				if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
						$value = substr($value, 1, -1);
				}

				if (getenv($key) === false || getenv($key) === '') {
						putenv($key . '=' . $value);
						$_ENV[$key] = $value;
				}
		}
}

function sanitizePair(string $pair): string
{
		$pair = strtolower(trim($pair));
		$pair = preg_replace('/[^a-z0-9]/', '', $pair) ?? 'btcidr';

		if ($pair === '') {
				return 'btcidr';
		}

		return $pair;
}

function resolveTradingInstanceKey(): string
{
		$instanceKey = trim((string) (getenv('TRADING_INSTANCE_KEY') ?: ''));
		return $instanceKey;
}

function envFloatValue(string $key, float $default): float
{
		$value = trim((string) (getenv($key) ?: ''));
		return is_numeric($value) ? (float) $value : $default;
}

function envBoolValue(string $key, bool $default): bool
{
		$value = trim((string) (getenv($key) ?: ''));
		if ($value === '') {
				return $default;
		}

		$value = strtolower($value);
		if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
				return true;
		}
		if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
				return false;
		}

		return $default;
}

function parseFloatEnvList(string $key, array $defaults): array
{
		$raw = trim((string) (getenv($key) ?: ''));
		if ($raw === '') {
				return $defaults;
		}

		$values = [];
		foreach (explode(',', $raw) as $part) {
				$part = trim($part);
				if ($part === '' || !is_numeric($part)) {
						continue;
				}
				$values[] = (float) $part;
		}

		return $values !== [] ? $values : $defaults;
}

function normalizeExternalBrowseUrl(string $value): string
{
		$value = trim($value);
		if ($value === '') {
				return '';
		}

		if (preg_match('~^https?://~i', $value) === 1) {
			return $value;
		}

		if (str_starts_with($value, '//')) {
			return 'https:' . $value;
		}

		return 'https://' . ltrim($value, '/');
}

function buildV3RuntimeConfig(string $pair): array
{
		$pair = sanitizePair($pair);
		$defaultPairDrop = [
				'btcidr' => 3.0,
				'ethidr' => 4.0,
				'dogeidr' => 7.0,
				'usdtidr' => 0.5,
				'xautidr' => 2.0,
		];
		$defaultAlloc = [
				'btcidr' => 35.0,
				'ethidr' => 25.0,
				'usdtidr' => 20.0,
				'xautidr' => 10.0,
				'dogeidr' => 10.0,
		];

		$gridLevels = parseFloatEnvList('GRID_LEVELS', [-2, -4, -6, -8, -11, -14, -18, -23]);
		$maxGridLevels = max(1, (int) envFloatValue('MAX_GRID_LEVELS', 8));
		if (count($gridLevels) > $maxGridLevels) {
				$gridLevels = array_slice($gridLevels, 0, $maxGridLevels);
		}

		$pairDropKey = 'PAIR_DROP_PCT_' . strtoupper($pair);
		$allocKey = 'ALLOC_' . strtoupper($pair);
		$pairDropDefault = $defaultPairDrop[$pair] ?? 3.0;
		$allocDefault = $defaultAlloc[$pair] ?? 0.0;

		return [
				'instanceKey' => resolveTradingInstanceKey(),
				'maxDailyLossPct' => envFloatValue('MAX_DAILY_LOSS_PCT', 3.0),
				'stopLossPct' => envFloatValue('STOP_LOSS_PCT', 2.0),
				'takeProfitPct' => envFloatValue('TAKE_PROFIT_PCT', 3.5),
				'maPeriod' => max(1, (int) envFloatValue('MA_PERIOD', 50)),
				'longMaPeriod' => max(1, (int) envFloatValue('MA_LONG_PERIOD', 200)),
				'atrPeriod' => max(1, (int) envFloatValue('ATR_PERIOD', 14)),
				'atrMultiplier' => envFloatValue('ATR_MULTIPLIER', 1.5),
				'atrGridMultiplier' => envFloatValue('ATR_GRID_MULTIPLIER', 1.2),
				'maBufferPct' => envFloatValue('MA_BUFFER_PCT', 0.3),
				'gridLevels' => $gridLevels,
				'maxGridLevels' => $maxGridLevels,
				'gridTakeProfitPct' => envFloatValue('GRID_TAKE_PROFIT_PCT', 2.0),
				'pairDropPct' => envFloatValue($pairDropKey, $pairDropDefault),
				'pairAllocationPct' => envFloatValue($allocKey, $allocDefault),
				'maxPairAllocationPct' => envFloatValue('MAX_PAIR_ALLOCATION_PCT', 35.0),
				'rebalanceEnabled' => envBoolValue('REBALANCE_ENABLED', true),
				'rebalanceIntervalHours' => envFloatValue('REBALANCE_INTERVAL_HOURS', 24.0),
				'harvestEnabled' => envBoolValue('HARVEST_ENABLED', true),
				'harvestLevelsPct' => parseFloatEnvList('HARVEST_LEVELS_PCT', [2, 4]),
				'makerEnabled' => envBoolValue('MARKET_MAKER_ENABLED', true),
				'makerLiveEnabled' => envBoolValue('MARKET_MAKER_LIVE_ENABLED', false),
				'makerRefreshSec' => envFloatValue('MAKER_REFRESH_SEC', 30.0),
				'makerLevelsPct' => parseFloatEnvList('MAKER_LEVELS_PCT', [0.3, 0.6]),
				'makerAtrSpreadMult' => envFloatValue('MAKER_ATR_SPREAD_MULT', 0.4),
				'makerTrendThresholdPct' => envFloatValue('MAKER_TREND_THRESHOLD_PCT', 2.0),
				'imbalanceEnabled' => envBoolValue('ORDERBOOK_IMBALANCE_ENABLED', true),
				'imbalanceThresholdPct' => envFloatValue('ORDERBOOK_IMBALANCE_THRESHOLD_PCT', 12.0),
				'liquidityFilterEnabled' => envBoolValue('LIQUIDITY_FILTER_ENABLED', false),
				'minTop5BidDepthIdr' => envFloatValue('MIN_TOP5_BID_DEPTH_IDR', 200000000.0),
				'whaleEnabled' => envBoolValue('WHALE_DETECTION_ENABLED', false),
				'whaleWallRatio' => envFloatValue('WHALE_WALL_RATIO', 2.5),
				'whaleConfirmSec' => envFloatValue('WHALE_CONFIRM_SEC', 10.0),
				'maxDailyTrades' => max(1, (int) envFloatValue('MAX_DAILY_TRADES', 40)),
		];
}

function parseTradingDsn(string $dsn): array
{
		$defaults = [
				'host' => 'localhost',
				'port' => 3306,
				'user' => 'root',
				'pass' => 'root',
				'db' => DEFAULT_TRADING_DB,
		];

		$dsn = trim($dsn);
		if ($dsn === '') {
				return $defaults;
		}

		$pattern = '/^([^:]+):([^@]*)@tcp\(([^:)]+)(?::([0-9]+))?\)\/([^?]+)(?:\?.*)?$/';
		if (!preg_match($pattern, $dsn, $matches)) {
				return $defaults;
		}

		return [
				'host' => $matches[3] !== '' ? $matches[3] : $defaults['host'],
				'port' => isset($matches[4]) && $matches[4] !== '' ? (int) $matches[4] : $defaults['port'],
				'user' => $matches[1] !== '' ? $matches[1] : $defaults['user'],
				'pass' => $matches[2],
				'db' => $matches[5] !== '' ? $matches[5] : $defaults['db'],
		];
}

function fetchJson(string $url): array
{
		$timeoutSeconds = 8;
		$raw = '';

		if (function_exists('curl_init')) {
				$ch = curl_init($url);
				curl_setopt_array($ch, [
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_CONNECTTIMEOUT => $timeoutSeconds,
						CURLOPT_TIMEOUT => $timeoutSeconds,
						CURLOPT_FOLLOWLOCATION => true,
						CURLOPT_USERAGENT => DASHBOARD_USER_AGENT,
				]);

				$raw = (string) curl_exec($ch);
				$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
				$curlError = curl_error($ch);

				if (($raw === '' || $httpCode >= 400) && stripos($curlError, 'SSL certificate problem') !== false) {
						curl_setopt_array($ch, [
								CURLOPT_SSL_VERIFYPEER => false,
								CURLOPT_SSL_VERIFYHOST => 0,
						]);
						$raw = (string) curl_exec($ch);
						$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
						$curlError = curl_error($ch);
				}

				curl_close($ch);

				if ($raw === '' || $httpCode >= 400) {
						throw new RuntimeException('HTTP request failed: ' . ($curlError !== '' ? $curlError : ('status ' . $httpCode)));
				}
		} else {
				$context = stream_context_create([
						'http' => [
								'method' => 'GET',
								'timeout' => $timeoutSeconds,
								'header' => "User-Agent: " . DASHBOARD_USER_AGENT . "\r\n",
								'follow_location' => 1,
								'max_redirects' => 5,
						],
				]);
				$raw = (string) @file_get_contents($url, false, $context);

				if ($raw === '') {
						$lastError = error_get_last();
						$lastErrorMessage = is_array($lastError) ? (string) ($lastError['message'] ?? '') : '';
						throw new RuntimeException('HTTP request failed while loading ' . $url . ($lastErrorMessage !== '' ? ': ' . $lastErrorMessage : ''));
				}
		}

		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
				throw new RuntimeException('Invalid JSON payload from ' . $url);
		}

		return $decoded;
}

function toFloatList(array $level): array
{
		return [
				'price' => isset($level[0]) ? (float) $level[0] : 0.0,
				'amount' => isset($level[1]) ? (float) $level[1] : 0.0,
		];
}

function sumTopDepthNotional(array $levels, int $limit): float
{
		$total = 0.0;
		foreach (array_slice($levels, 0, $limit) as $level) {
				if (!is_array($level)) {
						continue;
				}
				$price = (float) ($level['price'] ?? 0.0);
				$amount = (float) ($level['amount'] ?? 0.0);
				if ($price <= 0 || $amount <= 0) {
						continue;
				}
				$total += $price * $amount;
		}

		return $total;
}

function computeWhaleWallRatioFromLevels(array $levels, int $limit = 10): float
{
		$sample = array_slice($levels, 0, $limit);
		$notionals = [];
		foreach ($sample as $level) {
				if (!is_array($level)) {
						continue;
				}
				$price = (float) ($level['price'] ?? 0.0);
				$amount = (float) ($level['amount'] ?? 0.0);
				if ($price <= 0 || $amount <= 0) {
						continue;
				}
				$notionals[] = $price * $amount;
		}

		if ($notionals === []) {
				return 0.0;
		}

		$average = array_sum($notionals) / count($notionals);
		if ($average <= 0) {
				return 0.0;
		}

		return max($notionals) / $average;
}

function computeTrendStrengthPctValue(float $shortMA, float $longMA): float
{
		if ($shortMA <= 0 || $longMA <= 0) {
				return 0.0;
		}

		return abs($shortMA - $longMA) / $longMA * 100;
}

function buildV3MarketTelemetry(array $summary, array $orderbook, array $runtimeConfig, array $signal = []): array
{
		$buyLevels = is_array($orderbook['buy'] ?? null) ? $orderbook['buy'] : [];
		$sellLevels = is_array($orderbook['sell'] ?? null) ? $orderbook['sell'] : [];
		$top5BidDepth = sumTopDepthNotional($buyLevels, 5);
		$top5AskDepth = sumTopDepthNotional($sellLevels, 5);
		$totalTopDepth = $top5BidDepth + $top5AskDepth;
		$imbalancePct = $totalTopDepth > 0 ? (($top5BidDepth - $top5AskDepth) / $totalTopDepth) * 100 : 0.0;
		$whaleBuyRatio = computeWhaleWallRatioFromLevels($buyLevels);
		$whaleSellRatio = computeWhaleWallRatioFromLevels($sellLevels);
		$maValue = (float) ($signal['maValue'] ?? 0.0);
		$longMaValue = (float) ($signal['longMaValue'] ?? 0.0);
		$trendStrengthPct = computeTrendStrengthPctValue($maValue, $longMaValue);
		$makerMode = 'OFF';
		if (($runtimeConfig['makerEnabled'] ?? false) === true) {
				$makerMode = 'ACTIVE';
				$trendThreshold = (float) ($runtimeConfig['makerTrendThresholdPct'] ?? 0.0);
				if ($trendThreshold > 0 && $trendStrengthPct >= $trendThreshold) {
						$makerMode = 'TREND PAUSED';
				}
		}

		return [
				'top5BidDepthIdr' => $top5BidDepth,
				'top5AskDepthIdr' => $top5AskDepth,
				'orderbookImbalancePct' => $imbalancePct,
				'whaleBuyWallRatio' => $whaleBuyRatio,
				'whaleSellWallRatio' => $whaleSellRatio,
				'trendStrengthPct' => $trendStrengthPct,
				'makerMode' => $makerMode,
				'liquidityFilterPass' => $top5BidDepth <= 0 || $top5BidDepth >= (float) ($runtimeConfig['minTop5BidDepthIdr'] ?? 0.0),
				'imbalanceAlert' => abs($imbalancePct) >= (float) ($runtimeConfig['imbalanceThresholdPct'] ?? 0.0),
				'whaleAlert' => $whaleSellRatio >= (float) ($runtimeConfig['whaleWallRatio'] ?? 0.0),
		];
}

function openTradingDbConnection(): array
{
		$dbConfig = resolveTradingDbConfig();
		$host = (string) $dbConfig['host'];
		$port = (int) $dbConfig['port'];
		$dbUser = (string) $dbConfig['user'];
		$dbPass = (string) $dbConfig['pass'];
		$dbName = (string) $dbConfig['db'];

		$mysqli = @mysqli_init();
		if (!$mysqli) {
				return ['db' => null, 'error' => 'Cannot initialize MySQLi', 'dbName' => $dbName];
		}

		mysqli_options($mysqli, MYSQLI_OPT_CONNECT_TIMEOUT, 3);
		$connected = @mysqli_real_connect($mysqli, $host, $dbUser, $dbPass, $dbName, $port);
		if (!$connected) {
				$error = mysqli_connect_error();
				return ['db' => null, 'error' => 'DB connect failed: ' . ($error ?: 'unknown error'), 'dbName' => $dbName];
		}

		return ['db' => $mysqli, 'error' => '', 'dbName' => $dbName];
}

function buildFinanceAgent(array $summary, array $tradeChart, array $trades): array
{
		$last = (float) ($summary['last'] ?? 0);
		$high = (float) ($summary['high'] ?? 0);
		$low = (float) ($summary['low'] ?? 0);
		$buyTop = (float) ($summary['buyTop'] ?? 0);
		$sellTop = (float) ($summary['sellTop'] ?? 0);

		$score = 0;
		$reasons = [];

		if ($last > 0 && $high > $low) {
				$rangePosition = ($last - $low) / max(1.0, ($high - $low));
				if ($rangePosition >= 0.65) {
						$score += 1;
						$reasons[] = 'Price is in upper 24h range';
				} elseif ($rangePosition <= 0.35) {
						$score -= 1;
						$reasons[] = 'Price is in lower 24h range';
				}
		}

		if (count($tradeChart) >= 8) {
				$first = (float) ($tradeChart[0]['price'] ?? 0);
				$lastChart = (float) ($tradeChart[count($tradeChart) - 1]['price'] ?? 0);
				if ($first > 0 && $lastChart > 0) {
						$deltaPct = (($lastChart - $first) / $first) * 100;
						if ($deltaPct >= 0.2) {
								$score += 1;
								$reasons[] = 'Short-term momentum is positive';
						} elseif ($deltaPct <= -0.2) {
								$score -= 1;
								$reasons[] = 'Short-term momentum is negative';
						}
				}
		}

		$buyCount = 0;
		$sellCount = 0;
		foreach (array_slice($trades, 0, 40) as $trade) {
				$tradeType = (string) ($trade['type'] ?? '');
				if ($tradeType === 'buy') {
						$buyCount++;
				} elseif ($tradeType === 'sell') {
						$sellCount++;
				}
		}

		if (($buyCount + $sellCount) > 0) {
				if ($buyCount > $sellCount) {
						$score += 1;
						$reasons[] = 'Recent tape has more buy prints';
				} elseif ($sellCount > $buyCount) {
						$score -= 1;
						$reasons[] = 'Recent tape has more sell prints';
				}
		}

		if ($buyTop > 0 && $sellTop > 0) {
				$spreadPct = (($sellTop - $buyTop) / $sellTop) * 100;
				if ($spreadPct <= 0.15) {
						$score += 1;
						$reasons[] = 'Bid-ask spread is tight';
				} elseif ($spreadPct >= 0.6) {
						$score -= 1;
						$reasons[] = 'Bid-ask spread is wide';
				}
		}

		$action = 'HOLD';
		if ($score >= 2) {
				$action = 'BUY';
		} elseif ($score <= -2) {
				$action = 'SELL';
		}

		$confidence = min(95, 50 + (abs($score) * 12));
		if (empty($reasons)) {
				$reasons[] = 'Not enough directional signal from current market data';
		}

		return [
				'action' => $action,
				'confidence' => $confidence,
				'score' => $score,
				'reasons' => array_slice($reasons, 0, 3),
				'buyTradeCount' => $buyCount,
				'sellTradeCount' => $sellCount,
		];
}

function buildDashboardPayload(string $pair): array
{
		$errors = [];
		$depthData = [];
		$tradesData = [];
		$tickerData = [];

		try {
				$depthData = fetchJson(INDODAX_BASE_URL . 'depth/' . $pair);
		} catch (Throwable $e) {
				$errors[] = 'Depth data: ' . $e->getMessage();
		}

		try {
				$tradesData = fetchJson(INDODAX_BASE_URL . 'trades/' . $pair);
		} catch (Throwable $e) {
				$errors[] = 'Trades data: ' . $e->getMessage();
		}

		try {
				$tickerResponse = fetchJson(INDODAX_BASE_URL . 'ticker/' . $pair);
				$tickerData = is_array($tickerResponse['ticker'] ?? null) ? $tickerResponse['ticker'] : [];
		} catch (Throwable $e) {
				$errors[] = 'Ticker data: ' . $e->getMessage();
		}

		$buyLevelsRaw = is_array($depthData['buy'] ?? null) ? $depthData['buy'] : [];
		$sellLevelsRaw = is_array($depthData['sell'] ?? null) ? $depthData['sell'] : [];
		$buyLevels = array_map('toFloatList', array_slice($buyLevelsRaw, 0, 40));
		$sellLevels = array_map('toFloatList', array_slice($sellLevelsRaw, 0, 40));

		$trades = [];
		if (is_array($tradesData)) {
				foreach (array_slice($tradesData, 0, 80) as $trade) {
						if (!is_array($trade)) {
								continue;
						}

						$timestamp = isset($trade['date']) ? (int) $trade['date'] : time();
						$trades[] = [
								'tid' => (string) ($trade['tid'] ?? ''),
								'type' => (string) ($trade['type'] ?? 'unknown'),
								'price' => (float) ($trade['price'] ?? 0),
								'amount' => (float) ($trade['amount'] ?? 0),
								'date' => $timestamp,
								'timeLabel' => date('H:i:s', $timestamp),
						];
				}
		}

		$tradeChart = array_reverse(array_slice($trades, 0, 60));
		$bid = $buyLevels[0]['price'] ?? 0.0;
		$ask = $sellLevels[0]['price'] ?? 0.0;
		$spread = ($ask > 0 && $bid > 0) ? ($ask - $bid) : 0.0;

		$summary = [
				'last' => (float) ($tickerData['last'] ?? 0),
				'high' => (float) ($tickerData['high'] ?? 0),
				'low' => (float) ($tickerData['low'] ?? 0),
				'volBtc' => (float) ($tickerData['vol_btc'] ?? 0),
				'volIdr' => (float) ($tickerData['vol_idr'] ?? 0),
				'buyTop' => $bid,
				'sellTop' => $ask,
				'spread' => $spread,
		];

		return [
				'pair' => $pair,
				'errors' => $errors,
				'updatedAt' => date('Y-m-d H:i:s'),
				'summary' => $summary,
				'financeAgent' => buildFinanceAgent($summary, $tradeChart, $trades),
				'orderbook' => [
						'buy' => $buyLevels,
						'sell' => $sellLevels,
				],
				'trades' => $trades,
				'tradeChart' => $tradeChart,
		];
}

function fetchPortfolioReports(int $limit = 30, string $instanceKeyFilter = '', string $reportScope = 'all'): array
{
		$connection = openTradingDbConnection();
		$dbName = (string) ($connection['dbName'] ?? DEFAULT_TRADING_DB);
		$mysqli = $connection['db'];
		if (!$mysqli instanceof mysqli) {
				return ['rows' => [], 'error' => (string) ($connection['error'] ?? 'DB connect failed'), 'dbName' => $dbName, 'tradeWinStatsByInstance' => []];
		}

		$instanceKeyFilter = trim($instanceKeyFilter);
		$reportScope = $reportScope === 'today' ? 'today' : 'all';
		$dateFilterSql = $reportScope === 'today' ? 'dr.report_date = CURDATE()' : '1 = 1';
		$tradeDateFilterSql = $reportScope === 'today' ? 'DATE(created_at) = CURDATE()' : '1 = 1';

		$limit = max(1, min(60, $limit));
		$sql = '
			SELECT
				dr.instance_key,
				dr.report_date,
				dr.opening_value_idr,
				dr.closing_value_idr,
				dr.pnl_idr,
				dr.pnl_pct,
				dr.realized_pnl_idr,
				dr.trades_count
			FROM daily_reports dr
			WHERE ' . $dateFilterSql . ' ' . ($instanceKeyFilter !== '' ? 'AND dr.instance_key = ? ' : '') . '
			ORDER BY dr.report_date DESC, dr.instance_key ASC
			LIMIT ?';
		$stmt = mysqli_prepare($mysqli, $sql);
		if (!$stmt) {
				$error = mysqli_error($mysqli);
				mysqli_close($mysqli);
				return ['rows' => [], 'error' => 'DB query prepare failed: ' . $error, 'dbName' => $dbName];
		}

		if ($instanceKeyFilter !== '') {
				mysqli_stmt_bind_param($stmt, 'si', $instanceKeyFilter, $limit);
		} else {
				mysqli_stmt_bind_param($stmt, 'i', $limit);
		}
		mysqli_stmt_execute($stmt);
		$result = mysqli_stmt_get_result($stmt);

		$rows = [];
		if ($result instanceof mysqli_result) {
				while ($row = mysqli_fetch_assoc($result)) {
						if (!is_array($row)) {
								continue;
						}
						$rows[] = $row;
				}
		}

		mysqli_stmt_close($stmt);

		$tradeWinStats = ['total' => 0, 'winning' => 0];
		$tradeWinStatsByInstance = [];
		$winSql = 'SELECT COUNT(*) AS total_sells, SUM(CASE WHEN realized_pnl_idr > 0 THEN 1 ELSE 0 END) AS winning_sells FROM trades WHERE side = ? AND ' . $tradeDateFilterSql . ($instanceKeyFilter !== '' ? ' AND instance_key = ?' : '');
		$winStmt = mysqli_prepare($mysqli, $winSql);
		if ($winStmt) {
				$side = 'SELL';
				if ($instanceKeyFilter !== '') {
						mysqli_stmt_bind_param($winStmt, 'ss', $side, $instanceKeyFilter);
				} else {
						mysqli_stmt_bind_param($winStmt, 's', $side);
				}
				mysqli_stmt_execute($winStmt);
				$winResult = mysqli_stmt_get_result($winStmt);
				if ($winResult instanceof mysqli_result) {
						$winRow = mysqli_fetch_assoc($winResult);
						if (is_array($winRow)) {
								$tradeWinStats['total'] = (int) ($winRow['total_sells'] ?? 0);
								$tradeWinStats['winning'] = (int) ($winRow['winning_sells'] ?? 0);
						}
				}
				mysqli_stmt_close($winStmt);
		}

		$winByInstanceSql = 'SELECT instance_key, COUNT(*) AS total_sells, SUM(CASE WHEN realized_pnl_idr > 0 THEN 1 ELSE 0 END) AS winning_sells FROM trades WHERE side = ? AND ' . $tradeDateFilterSql . ($instanceKeyFilter !== '' ? ' AND instance_key = ?' : '') . ' GROUP BY instance_key';
		$winByInstanceStmt = mysqli_prepare($mysqli, $winByInstanceSql);
		if ($winByInstanceStmt) {
				$side = 'SELL';
				if ($instanceKeyFilter !== '') {
						mysqli_stmt_bind_param($winByInstanceStmt, 'ss', $side, $instanceKeyFilter);
				} else {
						mysqli_stmt_bind_param($winByInstanceStmt, 's', $side);
				}
				mysqli_stmt_execute($winByInstanceStmt);
				$winByInstanceResult = mysqli_stmt_get_result($winByInstanceStmt);
				if ($winByInstanceResult instanceof mysqli_result) {
						while ($winByInstanceRow = mysqli_fetch_assoc($winByInstanceResult)) {
								if (!is_array($winByInstanceRow)) {
										continue;
								}

								$instanceKeyValue = trim((string) ($winByInstanceRow['instance_key'] ?? ''));
								if ($instanceKeyValue === '') {
										continue;
								}

								$tradeWinStatsByInstance[$instanceKeyValue] = [
										'total' => (int) ($winByInstanceRow['total_sells'] ?? 0),
										'winning' => (int) ($winByInstanceRow['winning_sells'] ?? 0),
								];
						}
				}
				mysqli_stmt_close($winByInstanceStmt);
		}

		mysqli_close($mysqli);

		return ['rows' => $rows, 'error' => '', 'tradeWinStats' => $tradeWinStats, 'tradeWinStatsByInstance' => $tradeWinStatsByInstance, 'dbName' => $dbName];
}

function fetchAutoLearnAdvisor(int $lookbackDays = 14, string $instanceKeyFilter = '', float $profitTargetPct = 2.0): array
{
		$connection = openTradingDbConnection();
		$dbName = (string) ($connection['dbName'] ?? DEFAULT_TRADING_DB);
		$mysqli = $connection['db'];
		$stats = [
				'error' => (string) ($connection['error'] ?? ''),
				'dbName' => $dbName,
				'lookbackDays' => max(3, min(60, $lookbackDays)),
				'instanceKey' => trim($instanceKeyFilter),
				'buyCount' => 0,
				'sellCount' => 0,
				'winningSellCount' => 0,
				'winRate' => 0.0,
				'avgWinIdr' => 0.0,
				'avgLossIdr' => 0.0,
				'bestSellIdr' => 0.0,
				'worstSellIdr' => 0.0,
				'realizedTotalIdr' => 0.0,
				'reportDays' => 0,
				'positiveDays' => 0,
				'negativeDays' => 0,
				'totalReportPnlIdr' => 0.0,
				'avgDayPnlPct' => 0.0,
				'worstDayPnlPct' => 0.0,
				'bestDayPnlPct' => 0.0,
				'openPositions' => 0,
				'profitableOpenPositions' => 0,
				'underwaterOpenPositions' => 0,
				'nearTargetOpenPositions' => 0,
				'avgOpenProfitPct' => 0.0,
				'worstOpenProfitPct' => 0.0,
				'bestOpenProfitPct' => 0.0,
		];

		if (!$mysqli instanceof mysqli) {
				return $stats;
		}

		$instanceKeyFilter = trim($instanceKeyFilter);
		$lookbackDays = $stats['lookbackDays'];
		$tradeSince = (new DateTimeImmutable('-' . $lookbackDays . ' days'))->format('Y-m-d H:i:s');
		$reportSince = (new DateTimeImmutable('-' . $lookbackDays . ' days'))->format('Y-m-d');

		$sellSql = 'SELECT COUNT(*) AS total_sells, SUM(CASE WHEN realized_pnl_idr > 0 THEN 1 ELSE 0 END) AS winning_sells, AVG(CASE WHEN realized_pnl_idr > 0 THEN realized_pnl_idr END) AS avg_win_idr, AVG(CASE WHEN realized_pnl_idr < 0 THEN ABS(realized_pnl_idr) END) AS avg_loss_idr, MAX(realized_pnl_idr) AS best_sell_idr, MIN(realized_pnl_idr) AS worst_sell_idr, COALESCE(SUM(realized_pnl_idr), 0) AS realized_total_idr FROM trades WHERE side = ? AND created_at >= ?' . ($instanceKeyFilter !== '' ? ' AND instance_key = ?' : '');
		$sellStmt = mysqli_prepare($mysqli, $sellSql);
		if ($sellStmt) {
				$side = 'SELL';
				if ($instanceKeyFilter !== '') {
						mysqli_stmt_bind_param($sellStmt, 'sss', $side, $tradeSince, $instanceKeyFilter);
				} else {
						mysqli_stmt_bind_param($sellStmt, 'ss', $side, $tradeSince);
				}
				mysqli_stmt_execute($sellStmt);
				$result = mysqli_stmt_get_result($sellStmt);
				if ($result instanceof mysqli_result) {
						$row = mysqli_fetch_assoc($result);
						if (is_array($row)) {
								$stats['sellCount'] = (int) ($row['total_sells'] ?? 0);
								$stats['winningSellCount'] = (int) ($row['winning_sells'] ?? 0);
								$stats['avgWinIdr'] = (float) ($row['avg_win_idr'] ?? 0.0);
								$stats['avgLossIdr'] = (float) ($row['avg_loss_idr'] ?? 0.0);
								$stats['bestSellIdr'] = (float) ($row['best_sell_idr'] ?? 0.0);
								$stats['worstSellIdr'] = (float) ($row['worst_sell_idr'] ?? 0.0);
								$stats['realizedTotalIdr'] = (float) ($row['realized_total_idr'] ?? 0.0);
						}
				}
				mysqli_stmt_close($sellStmt);
		}

		$buySql = 'SELECT COUNT(*) AS total_buys FROM trades WHERE side = ? AND created_at >= ?' . ($instanceKeyFilter !== '' ? ' AND instance_key = ?' : '');
		$buyStmt = mysqli_prepare($mysqli, $buySql);
		if ($buyStmt) {
				$side = 'BUY';
				if ($instanceKeyFilter !== '') {
						mysqli_stmt_bind_param($buyStmt, 'sss', $side, $tradeSince, $instanceKeyFilter);
				} else {
						mysqli_stmt_bind_param($buyStmt, 'ss', $side, $tradeSince);
				}
				mysqli_stmt_execute($buyStmt);
				$result = mysqli_stmt_get_result($buyStmt);
				if ($result instanceof mysqli_result) {
						$row = mysqli_fetch_assoc($result);
						if (is_array($row)) {
								$stats['buyCount'] = (int) ($row['total_buys'] ?? 0);
						}
				}
				mysqli_stmt_close($buyStmt);
		}

		$reportSql = 'SELECT COUNT(*) AS report_days, SUM(CASE WHEN pnl_pct > 0 THEN 1 ELSE 0 END) AS positive_days, SUM(CASE WHEN pnl_pct < 0 THEN 1 ELSE 0 END) AS negative_days, COALESCE(SUM(pnl_idr), 0) AS total_pnl_idr, AVG(pnl_pct) AS avg_day_pnl_pct, MIN(pnl_pct) AS worst_day_pct, MAX(pnl_pct) AS best_day_pct FROM daily_reports WHERE report_date >= ?' . ($instanceKeyFilter !== '' ? ' AND instance_key = ?' : '');
		$reportStmt = mysqli_prepare($mysqli, $reportSql);
		if ($reportStmt) {
				if ($instanceKeyFilter !== '') {
						mysqli_stmt_bind_param($reportStmt, 'ss', $reportSince, $instanceKeyFilter);
				} else {
						mysqli_stmt_bind_param($reportStmt, 's', $reportSince);
				}
				mysqli_stmt_execute($reportStmt);
				$result = mysqli_stmt_get_result($reportStmt);
				if ($result instanceof mysqli_result) {
						$row = mysqli_fetch_assoc($result);
						if (is_array($row)) {
								$stats['reportDays'] = (int) ($row['report_days'] ?? 0);
								$stats['positiveDays'] = (int) ($row['positive_days'] ?? 0);
								$stats['negativeDays'] = (int) ($row['negative_days'] ?? 0);
								$stats['totalReportPnlIdr'] = (float) ($row['total_pnl_idr'] ?? 0.0);
								$stats['avgDayPnlPct'] = (float) ($row['avg_day_pnl_pct'] ?? 0.0);
								$stats['worstDayPnlPct'] = (float) ($row['worst_day_pct'] ?? 0.0);
								$stats['bestDayPnlPct'] = (float) ($row['best_day_pct'] ?? 0.0);
						}
				}
				mysqli_stmt_close($reportStmt);
		}

		$positionSql = 'SELECT COUNT(*) AS open_positions, SUM(CASE WHEN profit_pct > 0 THEN 1 ELSE 0 END) AS profitable_open_positions, SUM(CASE WHEN profit_pct < 0 THEN 1 ELSE 0 END) AS underwater_open_positions, SUM(CASE WHEN profit_pct > 0 AND profit_pct < ? THEN 1 ELSE 0 END) AS near_target_open_positions, AVG(profit_pct) AS avg_open_profit_pct, MIN(profit_pct) AS worst_open_profit_pct, MAX(profit_pct) AS best_open_profit_pct FROM (SELECT ((COALESCE(NULLIF((SELECT s.bid_price FROM signals s WHERE s.instance_key = p.instance_key AND s.pair = p.pair ORDER BY s.created_at DESC LIMIT 1), 0), (SELECT s.last_price FROM signals s WHERE s.instance_key = p.instance_key AND s.pair = p.pair ORDER BY s.created_at DESC LIMIT 1)) - p.avg_cost) / NULLIF(p.avg_cost, 0)) * 100 AS profit_pct FROM positions p WHERE p.quantity > 0' . ($instanceKeyFilter !== '' ? ' AND p.instance_key = ?' : '') . ') open_stats';
		$positionStmt = mysqli_prepare($mysqli, $positionSql);
		if ($positionStmt) {
				if ($instanceKeyFilter !== '') {
						mysqli_stmt_bind_param($positionStmt, 'ds', $profitTargetPct, $instanceKeyFilter);
				} else {
						mysqli_stmt_bind_param($positionStmt, 'd', $profitTargetPct);
				}
				mysqli_stmt_execute($positionStmt);
				$result = mysqli_stmt_get_result($positionStmt);
				if ($result instanceof mysqli_result) {
						$row = mysqli_fetch_assoc($result);
						if (is_array($row)) {
								$stats['openPositions'] = (int) ($row['open_positions'] ?? 0);
								$stats['profitableOpenPositions'] = (int) ($row['profitable_open_positions'] ?? 0);
								$stats['underwaterOpenPositions'] = (int) ($row['underwater_open_positions'] ?? 0);
								$stats['nearTargetOpenPositions'] = (int) ($row['near_target_open_positions'] ?? 0);
								$stats['avgOpenProfitPct'] = (float) ($row['avg_open_profit_pct'] ?? 0.0);
								$stats['worstOpenProfitPct'] = (float) ($row['worst_open_profit_pct'] ?? 0.0);
								$stats['bestOpenProfitPct'] = (float) ($row['best_open_profit_pct'] ?? 0.0);
						}
				}
				mysqli_stmt_close($positionStmt);
		}

		mysqli_close($mysqli);
		if ($stats['sellCount'] > 0) {
				$stats['winRate'] = ($stats['winningSellCount'] / $stats['sellCount']) * 100;
		}

		return $stats;
}

function buildAutoLearnRecommendations(array $advisor, array $runtimeConfig): array
{
		$recommendations = [];
		$gridTakeProfitPct = (float) ($runtimeConfig['gridTakeProfitPct'] ?? 2.0);
		$harvestLevels = array_values(array_map('floatval', (array) ($runtimeConfig['harvestLevelsPct'] ?? [])));
		$maxDailyLossPct = (float) ($runtimeConfig['maxDailyLossPct'] ?? 3.0);
		$maxPairAllocationPct = (float) ($runtimeConfig['maxPairAllocationPct'] ?? 35.0);
		$maxDailyTrades = (int) ($runtimeConfig['maxDailyTrades'] ?? 40);
		$stopLossPct = (float) ($runtimeConfig['stopLossPct'] ?? 2.0);

		if (($advisor['buyCount'] ?? 0) >= 6 && ($advisor['sellCount'] ?? 0) <= 1 && ($advisor['nearTargetOpenPositions'] ?? 0) >= 2) {
				$suggestedGridTakeProfitPct = max(1.2, round($gridTakeProfitPct - 0.5, 1));
				$firstHarvest = max(1.2, round(((float) ($harvestLevels[0] ?? $gridTakeProfitPct)) - 0.5, 1));
				$secondHarvestBase = (float) ($harvestLevels[1] ?? max($firstHarvest + 1.0, $gridTakeProfitPct + 1.0));
				$secondHarvest = max($firstHarvest + 1.0, round($secondHarvestBase - 0.5, 1));
				$recommendations[] = [
						'priority' => 'profit',
						'parameter' => 'GRID_TAKE_PROFIT_PCT',
						'current' => formatNumber($gridTakeProfitPct, 1) . '%',
						'suggested' => formatNumber($suggestedGridTakeProfitPct, 1) . '%',
						'title' => 'Take profit is likely too conservative for the current trade flow.',
						'reason' => 'Recent history shows many buys but almost no completed sells while ' . (int) ($advisor['nearTargetOpenPositions'] ?? 0) . ' open positions are already profitable but still below the current ' . formatNumber($gridTakeProfitPct, 1) . '% threshold.',
						'companion' => 'HARVEST_LEVELS_PCT ' . htmlspecialchars(formatCompactFloatList($harvestLevels), ENT_QUOTES, 'UTF-8') . '% -> ' . formatCompactFloatList([$firstHarvest, $secondHarvest]) . '%',
				];
		}

		if (($advisor['negativeDays'] ?? 0) >= 2 && ($advisor['underwaterOpenPositions'] ?? 0) >= 2) {
				$suggestedMaxPairAllocationPct = max(20.0, $maxPairAllocationPct - 5.0);
				$suggestedMaxDailyLossPct = max(1.0, min($maxDailyLossPct, 1.5));
				$recommendations[] = [
						'priority' => 'risk',
						'parameter' => 'MAX_PAIR_ALLOCATION_PCT',
						'current' => formatNumber($maxPairAllocationPct, 1) . '%',
						'suggested' => formatNumber($suggestedMaxPairAllocationPct, 1) . '%',
						'title' => 'Exposure per pair is high relative to recent drawdown behaviour.',
						'reason' => 'The last ' . (int) ($advisor['lookbackDays'] ?? 14) . ' days include ' . (int) ($advisor['negativeDays'] ?? 0) . ' losing report days and ' . (int) ($advisor['underwaterOpenPositions'] ?? 0) . ' open positions below water. Reducing per-pair allocation lowers risk concentration before stop-losses are needed.',
						'companion' => 'MAX_DAILY_LOSS_PCT ' . formatNumber($maxDailyLossPct, 1) . '% -> ' . formatNumber($suggestedMaxDailyLossPct, 1) . '%',
				];
		}

		if (($advisor['buyCount'] ?? 0) > max(12, (($advisor['sellCount'] ?? 0) + 1) * 4) && $maxDailyTrades > 24) {
				$suggestedMaxDailyTrades = max(24, $maxDailyTrades - 8);
				$recommendations[] = [
						'priority' => 'risk',
						'parameter' => 'MAX_DAILY_TRADES',
						'current' => (string) $maxDailyTrades,
						'suggested' => (string) $suggestedMaxDailyTrades,
						'title' => 'Daily trade frequency is high relative to realized exits.',
						'reason' => 'A high buy-to-sell ratio increases inventory risk and makes profit-taking lag. Slowing the trade cap gives the bot more time to harvest winners before adding more exposure.',
						'companion' => 'STOP_LOSS_PCT keep near ' . formatNumber($stopLossPct, 1) . '% while reducing inventory churn.',
				];
		}

		if ($recommendations === []) {
				$recommendations[] = [
						'priority' => 'hold',
						'parameter' => 'KEEP_CURRENT',
						'current' => 'Current settings',
						'suggested' => 'No change',
						'title' => 'No high-confidence parameter change stands out from the recent DB history.',
						'reason' => 'Recent realized PnL, trade win rate, and open position dispersion do not show a strong enough edge to justify automatic tuning yet.',
						'companion' => 'Keep monitoring another 3-5 trading days before changing the bot.',
				];
		}

		return $recommendations;
}

function fetchV3StrategyState(string $pair, int $longMaPeriod = 200, ?string $instanceKeyFilter = null): array
{
		$connection = openTradingDbConnection();
		$dbName = (string) ($connection['dbName'] ?? DEFAULT_TRADING_DB);
		$mysqli = $connection['db'];
		$instanceKey = $instanceKeyFilter === null ? resolveTradingInstanceKey() : trim($instanceKeyFilter);
		$aggregateAll = $instanceKey === '';
		$instanceLabel = $aggregateAll ? 'All instances' : $instanceKey;

		$state = [
				'error' => (string) ($connection['error'] ?? ''),
				'dbName' => $dbName,
				'instanceKey' => $instanceLabel,
				'priceHistoryCount' => 0,
				'priceHistoryFirstSeen' => '',
				'priceHistoryLastSeen' => '',
				'signal' => [],
				'position' => [],
				'portfolioSnapshot' => [],
				'makerOpen' => ['buy' => ['count' => 0, 'notional' => 0.0], 'sell' => ['count' => 0, 'notional' => 0.0], 'lastUpdatedAt' => ''],
				'dailyTradeCount' => 0,
				'harvestStage' => 0,
		];

		if (!$mysqli instanceof mysqli) {
				return $state;
		}

		$signalSql = 'SELECT instance_key, action, confidence, score, last_price, bid_price, ask_price, momentum_pct, spread_pct, buy_trades, sell_trades, regime, ma_value, atr_value, reason, created_at FROM signals WHERE pair = ?' . ($instanceKey !== '' ? ' AND instance_key = ?' : '') . ' ORDER BY created_at DESC LIMIT 1';
		$signalStmt = mysqli_prepare($mysqli, $signalSql);
		if ($signalStmt) {
				if ($instanceKey !== '') {
						mysqli_stmt_bind_param($signalStmt, 'ss', $pair, $instanceKey);
				} else {
						mysqli_stmt_bind_param($signalStmt, 's', $pair);
				}
				mysqli_stmt_execute($signalStmt);
				$result = mysqli_stmt_get_result($signalStmt);
				if ($result instanceof mysqli_result) {
						$row = mysqli_fetch_assoc($result);
						if (is_array($row)) {
								$state['signal'] = [
										'instanceKey' => $instanceLabel,
										'sourceInstanceKey' => (string) ($row['instance_key'] ?? ''),
										'action' => (string) ($row['action'] ?? ''),
										'confidence' => (int) ($row['confidence'] ?? 0),
										'score' => (int) ($row['score'] ?? 0),
										'lastPrice' => (float) ($row['last_price'] ?? 0.0),
										'bidPrice' => (float) ($row['bid_price'] ?? 0.0),
										'askPrice' => (float) ($row['ask_price'] ?? 0.0),
										'momentumPct' => (float) ($row['momentum_pct'] ?? 0.0),
										'spreadPct' => (float) ($row['spread_pct'] ?? 0.0),
										'buyTrades' => (int) ($row['buy_trades'] ?? 0),
										'sellTrades' => (int) ($row['sell_trades'] ?? 0),
										'regime' => (string) ($row['regime'] ?? ''),
										'maValue' => (float) ($row['ma_value'] ?? 0.0),
										'atrValue' => (float) ($row['atr_value'] ?? 0.0),
										'reason' => (string) ($row['reason'] ?? ''),
										'createdAt' => (string) ($row['created_at'] ?? ''),
								];
						}
				}
				mysqli_stmt_close($signalStmt);
		}

		$longMaPeriod = max(1, $longMaPeriod);
		$priceHistoryInstanceKey = $aggregateAll ? trim((string) ($state['signal']['sourceInstanceKey'] ?? '')) : $instanceKey;
		$priceHistorySql = 'SELECT price_close, created_at FROM price_history WHERE pair = ?' . ($priceHistoryInstanceKey !== '' ? ' AND instance_key = ?' : '') . ' ORDER BY created_at DESC LIMIT ?';
		$priceHistoryStmt = mysqli_prepare($mysqli, $priceHistorySql);
		if ($priceHistoryStmt) {
				if ($priceHistoryInstanceKey !== '') {
						mysqli_stmt_bind_param($priceHistoryStmt, 'ssi', $pair, $priceHistoryInstanceKey, $longMaPeriod);
				} else {
						mysqli_stmt_bind_param($priceHistoryStmt, 'si', $pair, $longMaPeriod);
				}
				mysqli_stmt_execute($priceHistoryStmt);
				$result = mysqli_stmt_get_result($priceHistoryStmt);
				if ($result instanceof mysqli_result) {
						$sum = 0.0;
						$count = 0;
						$firstSeen = '';
						$lastSeen = '';
						while ($row = mysqli_fetch_assoc($result)) {
								if (!is_array($row)) {
										continue;
								}
								$createdAt = (string) ($row['created_at'] ?? '');
								if ($createdAt !== '') {
										if ($lastSeen === '') {
												$lastSeen = $createdAt;
										}
										$firstSeen = $createdAt;
								}
								$priceClose = (float) ($row['price_close'] ?? 0.0);
								if ($priceClose <= 0) {
										continue;
								}
								$sum += $priceClose;
								$count++;
						}
						if ($count >= $longMaPeriod) {
								$state['signal']['longMaValue'] = $sum / $count;
						}
						$state['priceHistoryCount'] = $count;
						$state['priceHistoryFirstSeen'] = $firstSeen;
						$state['priceHistoryLastSeen'] = $lastSeen;
				}
				mysqli_stmt_close($priceHistoryStmt);
		}

		$positionSql = $aggregateAll
				? 'SELECT COALESCE(SUM(quantity), 0) AS quantity, CASE WHEN COALESCE(SUM(quantity), 0) > 0 THEN COALESCE(SUM(quantity * avg_cost), 0) / SUM(quantity) ELSE 0 END AS avg_cost, COALESCE(MAX(grid_level), 0) AS grid_level, MAX(updated_at) AS updated_at FROM positions WHERE pair = ?'
				: 'SELECT instance_key, quantity, avg_cost, grid_level, updated_at FROM positions WHERE pair = ? AND instance_key = ? ORDER BY updated_at DESC LIMIT 1';
		$positionStmt = mysqli_prepare($mysqli, $positionSql);
		if ($positionStmt) {
				if (!$aggregateAll) {
						mysqli_stmt_bind_param($positionStmt, 'ss', $pair, $instanceKey);
				} else {
						mysqli_stmt_bind_param($positionStmt, 's', $pair);
				}
				mysqli_stmt_execute($positionStmt);
				$result = mysqli_stmt_get_result($positionStmt);
				if ($result instanceof mysqli_result) {
						$row = mysqli_fetch_assoc($result);
						if (is_array($row)) {
								$state['position'] = [
										'instanceKey' => $instanceLabel,
										'quantity' => (float) ($row['quantity'] ?? 0.0),
										'avgCost' => (float) ($row['avg_cost'] ?? 0.0),
										'gridLevel' => (int) ($row['grid_level'] ?? 0),
										'updatedAt' => (string) ($row['updated_at'] ?? ''),
								];
						}
				}
				mysqli_stmt_close($positionStmt);
		}

		$snapshotSql = $aggregateAll
				? 'SELECT COALESCE(SUM(ps.cash_idr), 0) AS cash_idr, COALESCE(SUM(ps.positions_value_idr), 0) AS positions_value_idr, COALESCE(SUM(ps.total_value_idr), 0) AS total_value_idr, COALESCE(SUM(ps.unrealized_pnl_idr), 0) AS unrealized_pnl_idr, COALESCE(SUM(ps.realized_pnl_cum_idr), 0) AS realized_pnl_cum_idr, MAX(ps.created_at) AS created_at FROM portfolio_snapshots ps INNER JOIN (SELECT instance_key, MAX(created_at) AS created_at FROM portfolio_snapshots GROUP BY instance_key) latest ON latest.instance_key = ps.instance_key AND latest.created_at = ps.created_at'
				: 'SELECT instance_key, cash_idr, positions_value_idr, total_value_idr, unrealized_pnl_idr, realized_pnl_cum_idr, created_at FROM portfolio_snapshots WHERE instance_key = ? ORDER BY created_at DESC LIMIT 1';
		$snapshotStmt = mysqli_prepare($mysqli, $snapshotSql);
		if ($snapshotStmt) {
				if (!$aggregateAll) {
						mysqli_stmt_bind_param($snapshotStmt, 's', $instanceKey);
				}
				mysqli_stmt_execute($snapshotStmt);
				$result = mysqli_stmt_get_result($snapshotStmt);
				if ($result instanceof mysqli_result) {
						$row = mysqli_fetch_assoc($result);
						if (is_array($row)) {
								$state['portfolioSnapshot'] = [
										'instanceKey' => $instanceLabel,
										'cashIdr' => (float) ($row['cash_idr'] ?? 0.0),
										'positionsValueIdr' => (float) ($row['positions_value_idr'] ?? 0.0),
										'totalValueIdr' => (float) ($row['total_value_idr'] ?? 0.0),
										'unrealizedPnlIdr' => (float) ($row['unrealized_pnl_idr'] ?? 0.0),
										'realizedPnlCumIdr' => (float) ($row['realized_pnl_cum_idr'] ?? 0.0),
										'createdAt' => (string) ($row['created_at'] ?? ''),
								];
						}
				}
				mysqli_stmt_close($snapshotStmt);
		}

		$makerSql = 'SELECT side, COUNT(*) AS total_open, COALESCE(SUM(notional_idr), 0) AS total_notional, MAX(COALESCE(updated_at, created_at)) AS last_seen FROM maker_quotes WHERE pair = ? AND status = ?' . ($instanceKey !== '' ? ' AND instance_key = ?' : '') . ' GROUP BY side';
		$makerStmt = mysqli_prepare($mysqli, $makerSql);
		if ($makerStmt) {
				$status = 'OPEN';
				if ($instanceKey !== '') {
						mysqli_stmt_bind_param($makerStmt, 'sss', $pair, $status, $instanceKey);
				} else {
						mysqli_stmt_bind_param($makerStmt, 'ss', $pair, $status);
				}
				mysqli_stmt_execute($makerStmt);
				$result = mysqli_stmt_get_result($makerStmt);
				if ($result instanceof mysqli_result) {
						while ($row = mysqli_fetch_assoc($result)) {
								if (!is_array($row)) {
										continue;
								}
								$side = strtolower((string) ($row['side'] ?? ''));
								if (!isset($state['makerOpen'][$side])) {
										continue;
								}
								$state['makerOpen'][$side] = [
										'count' => (int) ($row['total_open'] ?? 0),
										'notional' => (float) ($row['total_notional'] ?? 0.0),
								];
								$lastSeen = (string) ($row['last_seen'] ?? '');
								if ($lastSeen > (string) $state['makerOpen']['lastUpdatedAt']) {
										$state['makerOpen']['lastUpdatedAt'] = $lastSeen;
								}
						}
				}
				mysqli_stmt_close($makerStmt);
		}

		$tradeSql = 'SELECT COUNT(*) AS total_trades FROM trades WHERE pair = ? AND DATE(created_at) = CURDATE()' . ($instanceKey !== '' ? ' AND instance_key = ?' : '');
		$tradeStmt = mysqli_prepare($mysqli, $tradeSql);
		if ($tradeStmt) {
				if ($instanceKey !== '') {
						mysqli_stmt_bind_param($tradeStmt, 'ss', $pair, $instanceKey);
				} else {
						mysqli_stmt_bind_param($tradeStmt, 's', $pair);
				}
				mysqli_stmt_execute($tradeStmt);
				$result = mysqli_stmt_get_result($tradeStmt);
				if ($result instanceof mysqli_result) {
						$row = mysqli_fetch_assoc($result);
						if (is_array($row)) {
								$state['dailyTradeCount'] = (int) ($row['total_trades'] ?? 0);
						}
				}
				mysqli_stmt_close($tradeStmt);
		}

		$harvestSql = $aggregateAll
				? 'SELECT COALESCE(MAX(CAST(state_value AS UNSIGNED)), 0) AS state_value FROM bot_state WHERE state_key = ?'
				: 'SELECT state_value FROM bot_state WHERE state_key = ? AND instance_key = ? ORDER BY updated_at DESC LIMIT 1';
		$harvestStmt = mysqli_prepare($mysqli, $harvestSql);
		if ($harvestStmt) {
				$stateKey = 'harvest_stage_' . $pair;
				if (!$aggregateAll) {
						mysqli_stmt_bind_param($harvestStmt, 'ss', $stateKey, $instanceKey);
				} else {
						mysqli_stmt_bind_param($harvestStmt, 's', $stateKey);
				}
				mysqli_stmt_execute($harvestStmt);
				$result = mysqli_stmt_get_result($harvestStmt);
				if ($result instanceof mysqli_result) {
						$row = mysqli_fetch_assoc($result);
						if (is_array($row)) {
								$state['harvestStage'] = (int) ($row['state_value'] ?? 0);
						}
				}
				mysqli_stmt_close($harvestStmt);
		}

		mysqli_close($mysqli);
		return $state;
}

function buildV3StrategySummary(string $pair, array $runtimeConfig, array $strategyState, array $marketTelemetry): array
{
		$signal = is_array($strategyState['signal'] ?? null) ? $strategyState['signal'] : [];
		$position = is_array($strategyState['position'] ?? null) ? $strategyState['position'] : [];
		$snapshot = is_array($strategyState['portfolioSnapshot'] ?? null) ? $strategyState['portfolioSnapshot'] : [];
		$lastPrice = (float) ($signal['lastPrice'] ?? 0.0);
		$positionQty = (float) ($position['quantity'] ?? 0.0);
		$avgCost = (float) ($position['avgCost'] ?? 0.0);
		$positionValue = $positionQty > 0 && $lastPrice > 0 ? $positionQty * $lastPrice : 0.0;
		$portfolioTotal = (float) ($snapshot['totalValueIdr'] ?? 0.0);
		$allocationPct = $portfolioTotal > 0 ? ($positionValue / $portfolioTotal) * 100 : 0.0;
		$unrealizedPnl = $positionQty > 0 && $lastPrice > 0 && $avgCost > 0 ? ($lastPrice - $avgCost) * $positionQty : 0.0;
		$unrealizedPct = $avgCost > 0 && $lastPrice > 0 ? (($lastPrice - $avgCost) / $avgCost) * 100 : 0.0;

		return [
				'instanceKey' => (string) (($signal['instanceKey'] ?? '') !== '' ? $signal['instanceKey'] : ($strategyState['instanceKey'] ?? '')),
				'positionValueIdr' => $positionValue,
				'allocationPct' => $allocationPct,
				'unrealizedPnlIdr' => $unrealizedPnl,
				'unrealizedPnlPct' => $unrealizedPct,
				'gridSlotsUsed' => max(0, (int) ($position['gridLevel'] ?? 0)),
				'gridSlotsMax' => max(1, (int) ($runtimeConfig['maxGridLevels'] ?? 1)),
				'harvestStage' => (int) ($strategyState['harvestStage'] ?? 0),
				'dailyTradeCount' => (int) ($strategyState['dailyTradeCount'] ?? 0),
				'dailyTradeCap' => (int) ($runtimeConfig['maxDailyTrades'] ?? 0),
				'makerBuyOpen' => (int) ($strategyState['makerOpen']['buy']['count'] ?? 0),
				'makerSellOpen' => (int) ($strategyState['makerOpen']['sell']['count'] ?? 0),
				'makerBuyNotionalIdr' => (float) ($strategyState['makerOpen']['buy']['notional'] ?? 0.0),
				'makerSellNotionalIdr' => (float) ($strategyState['makerOpen']['sell']['notional'] ?? 0.0),
				'makerLastUpdatedAt' => (string) ($strategyState['makerOpen']['lastUpdatedAt'] ?? ''),
				'positionUpdatedAt' => (string) ($position['updatedAt'] ?? ''),
				'snapshotUpdatedAt' => (string) ($snapshot['createdAt'] ?? ''),
				'marketTelemetry' => $marketTelemetry,
		];
}

function resolveTradingDbConfig(): array
{
		$dsn = (string) (getenv('TRADING_DB_DSN') ?: '');
		$parsed = parseTradingDsn($dsn);

		$hostPortOverride = trim((string) (getenv('TRADING_DB_HOST') ?: ''));
		$dbUserOverride = trim((string) (getenv('TRADING_DB_USER') ?: ''));
		$dbPassOverride = (string) (getenv('TRADING_DB_PASS') ?: '');
		$dbNameOverride = trim((string) (getenv('TRADING_DB_NAME') ?: ''));

		$host = (string) $parsed['host'];
		$port = (int) $parsed['port'];
		$dbUser = (string) $parsed['user'];
		$dbPass = (string) $parsed['pass'];
		$dbName = (string) $parsed['db'];

		if ($hostPortOverride !== '') {
				if (str_contains($hostPortOverride, ':')) {
						[$h, $p] = explode(':', $hostPortOverride, 2);
						$host = trim($h) !== '' ? trim($h) : $host;
						$portNum = (int) trim($p);
						if ($portNum > 0) {
								$port = $portNum;
						}
				} else {
						$host = $hostPortOverride;
				}
		}

		if ($dbUserOverride !== '') {
				$dbUser = $dbUserOverride;
		}
		if ($dbNameOverride !== '') {
				$dbName = $dbNameOverride;
		}
		if ($dbPassOverride !== '') {
				$dbPass = $dbPassOverride;
		}

		return [
				'host' => $host,
				'port' => $port,
				'user' => $dbUser,
				'pass' => $dbPass,
				'db' => $dbName,
		];
}

function buildInvestmentCostModel(): array
{
		$hardwareCostIdr = envFloatValue('INVESTMENT_HARDWARE_COST_IDR', DEFAULT_INVESTMENT_HARDWARE_COST_IDR);
		$electricityIdrPerKwh = envFloatValue('INVESTMENT_ELECTRICITY_IDR_PER_KWH', DEFAULT_INVESTMENT_ELECTRICITY_IDR_PER_KWH);
		$powerWatt = envFloatValue('INVESTMENT_POWER_WATT', DEFAULT_INVESTMENT_POWER_WATT);
		$amortizationDays = max(1, (int) envFloatValue('INVESTMENT_AMORTIZATION_DAYS', DEFAULT_INVESTMENT_AMORTIZATION_DAYS));
		$hardwareDailyCost = $hardwareCostIdr / $amortizationDays;
		$kwhPerDay = ($powerWatt / 1000.0) * 24.0;
		$electricityDailyCost = $kwhPerDay * $electricityIdrPerKwh;
		$totalDailyCost = $hardwareDailyCost + $electricityDailyCost;

		return [
				'hardwareCostIdr' => $hardwareCostIdr,
				'electricityIdrPerKwh' => $electricityIdrPerKwh,
				'powerWatt' => $powerWatt,
				'amortizationDays' => $amortizationDays,
				'kwhPerDay' => $kwhPerDay,
				'hardwareDailyCostIdr' => $hardwareDailyCost,
				'electricityDailyCostIdr' => $electricityDailyCost,
				'totalDailyCostIdr' => $totalDailyCost,
		];
}

function enrichPortfolioReportsWithCost(array $reports, array $investmentCostModel): array
{
		$hardwareDailyCost = (float) ($investmentCostModel['hardwareDailyCostIdr'] ?? 0.0);
		$electricityDailyCost = (float) ($investmentCostModel['electricityDailyCostIdr'] ?? 0.0);
		$dailyCost = (float) ($investmentCostModel['totalDailyCostIdr'] ?? ($hardwareDailyCost + $electricityDailyCost));

		$enriched = [];
		foreach ($reports as $row) {
				if (!is_array($row)) {
						continue;
				}

				$opening = (float) ($row['opening_value_idr'] ?? 0.0);
				$pnl = (float) ($row['pnl_idr'] ?? 0.0);
				$netAfterCost = $pnl - $dailyCost;
				$predictedProfitRatePct = $opening > 0 ? ($dailyCost / $opening) * 100 : 0.0;
				$costCoveragePct = $dailyCost > 0 ? ($pnl / $dailyCost) * 100 : 0.0;

				$row['hardware_daily_cost_idr'] = $hardwareDailyCost;
				$row['electricity_daily_cost_idr'] = $electricityDailyCost;
				$row['daily_cost_idr'] = $dailyCost;
				$row['net_after_cost_idr'] = $netAfterCost;
				$row['predicted_profit_rate_pct'] = $predictedProfitRatePct;
				$row['cost_coverage_pct'] = $costCoveragePct;

				$enriched[] = $row;
		}

		return $enriched;
}

function summarizePortfolioReports(array $reports, array $tradeWinStats = []): array
{
		$totalOpening = 0.0;
		$totalClosing = 0.0;
		$totalPnl = 0.0;
		$totalRealized = 0.0;
		$totalCost = 0.0;
		$totalHardwareCost = 0.0;
		$totalElectricityCost = 0.0;
		$totalNetAfterCost = 0.0;
		$totalPredictedProfitRatePct = 0.0;
		$predictedProfitRateDays = 0;
		$profitableAfterCostDays = 0;
		$totalTrades = 0;
		$latestDate = '';

		foreach ($reports as $row) {
				if (!is_array($row)) {
						continue;
				}

				$totalOpening += (float) ($row['opening_value_idr'] ?? 0);
				$totalClosing += (float) ($row['closing_value_idr'] ?? 0);
				$pnl = (float) ($row['pnl_idr'] ?? 0);
				$totalPnl += $pnl;
				$totalRealized += (float) ($row['realized_pnl_idr'] ?? 0);
				$dailyCost = (float) ($row['daily_cost_idr'] ?? 0.0);
				$dailyHardwareCost = (float) ($row['hardware_daily_cost_idr'] ?? 0.0);
				$dailyElectricityCost = (float) ($row['electricity_daily_cost_idr'] ?? 0.0);
				$netAfterCost = (float) ($row['net_after_cost_idr'] ?? ($pnl - $dailyCost));
				$predictedProfitRatePct = (float) ($row['predicted_profit_rate_pct'] ?? 0.0);
				$opening = (float) ($row['opening_value_idr'] ?? 0.0);
				$totalCost += $dailyCost;
				$totalHardwareCost += $dailyHardwareCost;
				$totalElectricityCost += $dailyElectricityCost;
				$totalNetAfterCost += $netAfterCost;
				if ($opening > 0) {
						$totalPredictedProfitRatePct += $predictedProfitRatePct;
						$predictedProfitRateDays++;
				}
				if ($netAfterCost >= 0) {
						$profitableAfterCostDays++;
				}
				$totalTrades += (int) ($row['trades_count'] ?? 0);

				$date = (string) ($row['report_date'] ?? '');
				if ($date > $latestDate) {
						$latestDate = $date;
				}
		}

		$reportCount = count($reports);
		$totalSellTrades = (int) ($tradeWinStats['total'] ?? 0);
		$winningSellTrades = (int) ($tradeWinStats['winning'] ?? 0);
		$winRate = $totalSellTrades > 0 ? ($winningSellTrades / $totalSellTrades) * 100 : 0.0;
		$totalPnlPct = $totalOpening > 0 ? ($totalPnl / $totalOpening) * 100 : 0.0;
		$avgPredictedProfitRatePct = $predictedProfitRateDays > 0 ? ($totalPredictedProfitRatePct / $predictedProfitRateDays) : 0.0;
		$netAfterCostPct = $totalOpening > 0 ? ($totalNetAfterCost / $totalOpening) * 100 : 0.0;
		$costCoveragePct = $totalCost > 0 ? ($totalPnl / $totalCost) * 100 : 0.0;

		return [
				'reportCount' => $reportCount,
				'totalOpening' => $totalOpening,
				'totalClosing' => $totalClosing,
				'totalPnl' => $totalPnl,
				'totalPnlPct' => $totalPnlPct,
				'totalRealized' => $totalRealized,
				'totalCost' => $totalCost,
				'totalHardwareCost' => $totalHardwareCost,
				'totalElectricityCost' => $totalElectricityCost,
				'totalNetAfterCost' => $totalNetAfterCost,
				'netAfterCostPct' => $netAfterCostPct,
				'avgPredictedProfitRatePct' => $avgPredictedProfitRatePct,
				'costCoveragePct' => $costCoveragePct,
				'profitableAfterCostDays' => $profitableAfterCostDays,
				'totalTrades' => $totalTrades,
				'totalSellTrades' => $totalSellTrades,
				'winningSellTrades' => $winningSellTrades,
				'winRate' => $winRate,
				'latestDate' => $latestDate,
		];
}

function loadTelegramChatStoreData(string $path): array
{
		if (!is_file($path)) {
				return ['chatIds' => [], 'displayNames' => []];
		}

		$raw = @file_get_contents($path);
		if (!is_string($raw) || $raw === '') {
				return ['chatIds' => [], 'displayNames' => []];
		}

		$decoded = json_decode($raw, true);
		$chatIds = is_array($decoded['chat_ids'] ?? null) ? $decoded['chat_ids'] : [];
		$displayNamesRaw = is_array($decoded['display_names'] ?? null) ? $decoded['display_names'] : [];
		$normalized = [];
		$displayNames = [];

		foreach ($chatIds as $chatId) {
				$chatId = trim((string) $chatId);
				if ($chatId === '') {
						continue;
				}
				$normalized[] = $chatId;
		}

		foreach ($displayNamesRaw as $chatId => $displayName) {
				$chatId = trim((string) $chatId);
				$displayName = trim((string) $displayName);
				if ($chatId === '' || $displayName === '') {
						continue;
				}
				$displayNames[$chatId] = $displayName;
		}

		return [
				'chatIds' => array_values(array_unique($normalized)),
				'displayNames' => $displayNames,
		];
}

function formatPortfolioInstanceLabel(string $instanceKey, array $telegramDisplayNames = []): string
{
		$instanceKey = trim($instanceKey);
		if ($instanceKey === '') {
				return '-';
		}

		if (str_starts_with($instanceKey, 'telegram:')) {
				$chatId = substr($instanceKey, strlen('telegram:'));
				$displayName = trim((string) ($telegramDisplayNames[$chatId] ?? ''));
				if ($displayName !== '') {
						return $displayName;
				}
		}

		return $instanceKey;
}

function sanitizeInstanceKeyFilter(string $value): string
{
		$value = strtolower(trim($value));
		if ($value === '') {
				return '';
		}
		if ($value === 'all') {
				return '';
		}

		return preg_replace('/[^a-zA-Z0-9:_-]/', '', $value) ?? '';
}

function sanitizePortfolioReportScope(string $value): string
{
		$value = strtolower(trim($value));
		return $value === 'today' ? 'today' : 'all';
}

function filterPortfolioReportsByInstance(array $reports, string $instanceKey): array
{
		if ($instanceKey === '') {
				return $reports;
		}

		$filtered = [];
		foreach ($reports as $report) {
				if (!is_array($report)) {
						continue;
				}

				if ((string) ($report['instance_key'] ?? '') !== $instanceKey) {
						continue;
				}

				$filtered[] = $report;
		}

		return $filtered;
}

function buildPortfolioFilterUrl(string $pair, string $instanceKey, string $reportScope = 'all'): string
{
		$query = [
				'pair' => $pair,
				'portfolio_scope' => sanitizePortfolioReportScope($reportScope),
		];
		if ($instanceKey !== '') {
				$query['portfolio_instance'] = $instanceKey;
		}

		return '?' . http_build_query($query);
}

function buildTelegramPortfolioCards(array $reports, array $chatIds, array $displayNames = [], array $tradeWinStatsByInstance = [], string $selectedInstanceKey = ''): array
{
		$instanceBuckets = [];

		foreach ($reports as $report) {
				if (!is_array($report)) {
						continue;
				}

				$instanceKey = trim((string) ($report['instance_key'] ?? ''));
				if ($instanceKey === '') {
						continue;
				}

				$instanceBuckets[$instanceKey][] = $report;
		}

		foreach ($chatIds as $chatId) {
				$chatId = trim((string) $chatId);
				if ($chatId === '') {
						continue;
				}

				$instanceKey = 'telegram:' . $chatId;
				$instanceBuckets[$instanceKey] = $instanceBuckets[$instanceKey] ?? [];
		}

		if ($instanceBuckets === []) {
				return [];
		}

		ksort($instanceBuckets, SORT_NATURAL);
		$cards = [];

		foreach ($instanceBuckets as $instanceKey => $instanceReports) {
				$chatId = str_starts_with($instanceKey, 'telegram:') ? substr($instanceKey, strlen('telegram:')) : '';
				$displayName = formatPortfolioInstanceLabel($instanceKey, $displayNames);
				$summary = summarizePortfolioReports($instanceReports, $tradeWinStatsByInstance[$instanceKey] ?? []);
				$latestClosing = 0.0;
				$latestOpening = 0.0;
				$latestReportDate = '';

				foreach ($instanceReports as $report) {
						if (!is_array($report)) {
								continue;
						}

						$reportDate = (string) ($report['report_date'] ?? '');
						if ($reportDate >= $latestReportDate) {
								$latestReportDate = $reportDate;
								$latestOpening = (float) ($report['opening_value_idr'] ?? 0.0);
								$latestClosing = (float) ($report['closing_value_idr'] ?? 0.0);
						}
				}

				$cards[] = [
						'chatId' => $chatId,
						'instanceKey' => $instanceKey,
						'displayName' => $displayName,
						'reportCount' => (int) ($summary['reportCount'] ?? 0),
						'totalPnl' => (float) ($summary['totalPnl'] ?? 0.0),
						'totalRealized' => (float) ($summary['totalRealized'] ?? 0.0),
						'totalTrades' => (int) ($summary['totalTrades'] ?? 0),
						'totalSellTrades' => (int) ($summary['totalSellTrades'] ?? 0),
						'winningSellTrades' => (int) ($summary['winningSellTrades'] ?? 0),
						'winRate' => (float) ($summary['winRate'] ?? 0.0),
						'latestDate' => (string) ($summary['latestDate'] ?? ''),
						'latestOpeningValueIdr' => $latestOpening,
						'latestClosingValueIdr' => $latestClosing,
						'hasReports' => $instanceReports !== [],
						'isSelected' => $selectedInstanceKey !== '' && $selectedInstanceKey === $instanceKey,
				];
		}

		return $cards;
}

function formatNumber(float $value, int $decimals = 0): string
{
		return number_format($value, $decimals, '.', ',');
}

function formatSellWinRateSummary(array $summary): string
{
		$totalSellTrades = (int) ($summary['totalSellTrades'] ?? 0);
		$winningSellTrades = (int) ($summary['winningSellTrades'] ?? 0);

		if ($totalSellTrades <= 0) {
				return 'N/A (0 sells)';
		}

		return formatNumber((float) ($summary['winRate'] ?? 0.0), 2) . '% (' . $winningSellTrades . '/' . $totalSellTrades . ' sells)';
}

function formatCompactFloatList(array $values, int $decimals = 1): string
{
		$parts = [];
		foreach ($values as $value) {
				$parts[] = rtrim(rtrim(number_format((float) $value, $decimals, '.', ''), '0'), '.');
		}

		return implode(', ', $parts);
}

function resolveMimicBinaryPath(): string
{
		$configured = trim((string) (getenv('MIMIC_WEB_COMMAND') ?: ''));
		$candidates = [];
		if ($configured !== '') {
				$candidates[] = $configured;
		}

		$rootDir = dirname(__DIR__);
		$candidates[] = $rootDir . DIRECTORY_SEPARATOR . 'windows' . DIRECTORY_SEPARATOR . 'trading-bot-v3.exe';
		$candidates[] = $rootDir . DIRECTORY_SEPARATOR . 'raspi' . DIRECTORY_SEPARATOR . 'trading-bot-v3';
		$candidates[] = $rootDir . DIRECTORY_SEPARATOR . 'trading-bot-v3.exe';
		$candidates[] = $rootDir . DIRECTORY_SEPARATOR . 'trading-bot-v3';

		foreach ($candidates as $candidate) {
				$candidate = trim((string) $candidate);
				if ($candidate === '') {
						continue;
				}

				if (is_file($candidate)) {
						return $candidate;
				}
		}

		return '';
}

function isPhpFunctionCallable(string $name): bool
{
		if (!function_exists($name)) {
				return false;
		}

		$disabledRaw = ini_get('disable_functions');
		if (!is_string($disabledRaw) || trim($disabledRaw) === '') {
				return true;
		}

		$disabled = array_map('trim', explode(',', strtolower($disabledRaw)));
		return !in_array(strtolower($name), $disabled, true);
}

function runMimicChatPromptWithShell(string $binaryPath, string $prompt, bool $autoApprove): array
{
		$binaryArg = escapeshellarg($binaryPath);
		$promptArg = escapeshellarg($prompt);
		$workDirArg = escapeshellarg(dirname($binaryPath));
		$approve = $autoApprove ? 'y' : 'n';
		$executorFlags = [
				'exec' => isPhpFunctionCallable('exec'),
				'shell_exec' => isPhpFunctionCallable('shell_exec'),
				'popen' => isPhpFunctionCallable('popen'),
				'system' => isPhpFunctionCallable('system'),
				'passthru' => isPhpFunctionCallable('passthru'),
		];

		if (DIRECTORY_SEPARATOR === '\\') {
				$command = 'cmd /C "cd /D ' . $workDirArg . ' && (echo ' . $approve . ') | ' . $binaryArg . ' -cli ' . $promptArg . ' 2>&1"';
		} else {
				$command = 'cd ' . $workDirArg . ' && printf ' . escapeshellarg($approve . "\n") . ' | ' . $binaryArg . ' -cli ' . $promptArg . ' 2>&1';
		}

		if ($executorFlags['exec']) {
				$lines = [];
				$exitCode = 0;
				exec($command, $lines, $exitCode);
				$output = trim(implode("\n", $lines));
				$error = $exitCode === 0 ? '' : 'Mimic process exited with code ' . $exitCode;

				return [
						'ok' => $exitCode === 0,
						'output' => $output,
						'error' => $error,
						'exitCode' => $exitCode,
				];
		}

		if ($executorFlags['shell_exec']) {
				$output = shell_exec($command);
				if (!is_string($output)) {
						return [
								'ok' => false,
								'output' => '',
								'error' => 'Failed to run mimic process via shell_exec.',
								'exitCode' => 126,
						];
				}

				return [
						'ok' => true,
						'output' => trim($output),
						'error' => '',
						'exitCode' => 0,
				];
		}

		if ($executorFlags['popen']) {
				$handle = popen($command, 'r');
				if (is_resource($handle)) {
						$output = stream_get_contents($handle);
						$exitCode = pclose($handle);
						$outputText = trim((string) $output);
						$error = $exitCode === 0 ? '' : 'Mimic process exited with code ' . $exitCode;

						return [
								'ok' => $exitCode === 0,
								'output' => $outputText,
								'error' => $error,
								'exitCode' => $exitCode,
						];
				}
		}

		if ($executorFlags['system']) {
				ob_start();
				$exitCode = 0;
				system($command, $exitCode);
				$output = ob_get_clean();
				$outputText = trim((string) $output);
				$error = $exitCode === 0 ? '' : 'Mimic process exited with code ' . $exitCode;

				return [
						'ok' => $exitCode === 0,
						'output' => $outputText,
						'error' => $error,
						'exitCode' => $exitCode,
				];
		}

		if ($executorFlags['passthru']) {
				ob_start();
				$exitCode = 0;
				passthru($command, $exitCode);
				$output = ob_get_clean();
				$outputText = trim((string) $output);
				$error = $exitCode === 0 ? '' : 'Mimic process exited with code ' . $exitCode;

				return [
						'ok' => $exitCode === 0,
						'output' => $outputText,
						'error' => $error,
						'exitCode' => $exitCode,
				];
		}

		$flagSummary = [];
		foreach ($executorFlags as $name => $isEnabled) {
				$flagSummary[] = $name . '=' . ($isEnabled ? 'on' : 'off');
		}

		return [
				'ok' => false,
				'output' => '',
				'error' => 'proc_open is unavailable and all fallback executors are disabled in PHP (' . implode(', ', $flagSummary) . ').',
				'exitCode' => 126,
		];
}

function runMimicChatPromptViaHttpBridge(string $endpoint, string $prompt, bool $autoApprove): array
{
		$endpoint = trim($endpoint);
		if ($endpoint === '') {
				return [
						'ok' => false,
						'output' => '',
						'error' => 'MIMIC_WEB_ENDPOINT is empty.',
						'exitCode' => 126,
				];
		}

		$payload = json_encode([
				'prompt' => $prompt,
				'autoApprove' => $autoApprove,
		], JSON_UNESCAPED_SLASHES);
		if (!is_string($payload)) {
				return [
						'ok' => false,
						'output' => '',
						'error' => 'Failed to encode bridge request payload.',
						'exitCode' => 126,
				];
		}

		$headers = [
				'Content-Type: application/json',
				'Accept: application/json',
				'User-Agent: ' . DASHBOARD_USER_AGENT,
		];
		$bridgeToken = trim((string) (getenv('MIMIC_WEB_ENDPOINT_TOKEN') ?: ''));
		if ($bridgeToken !== '') {
				$headers[] = 'Authorization: Bearer ' . $bridgeToken;
		}

		if (function_exists('curl_init')) {
				$ch = curl_init($endpoint);
				curl_setopt_array($ch, [
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_CONNECTTIMEOUT => 8,
						CURLOPT_TIMEOUT => 20,
						CURLOPT_POST => true,
						CURLOPT_HTTPHEADER => $headers,
						CURLOPT_POSTFIELDS => $payload,
				]);

				$raw = (string) curl_exec($ch);
				$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
				$curlError = trim((string) curl_error($ch));
				curl_close($ch);

				if ($raw === '' && $curlError !== '') {
						return [
								'ok' => false,
								'output' => '',
								'error' => 'Bridge request failed: ' . $curlError,
								'exitCode' => 126,
						];
				}

				$decoded = json_decode($raw, true);
				if (is_array($decoded)) {
						$decoded['ok'] = (bool) ($decoded['ok'] ?? false);
						$decoded['output'] = (string) ($decoded['output'] ?? '');
						$decoded['error'] = (string) ($decoded['error'] ?? '');
						$decoded['exitCode'] = (int) ($decoded['exitCode'] ?? ($httpCode >= 400 ? $httpCode : 0));
						return $decoded;
				}

				return [
						'ok' => $httpCode >= 200 && $httpCode < 300,
						'output' => trim($raw),
						'error' => $httpCode >= 200 && $httpCode < 300 ? '' : ('Bridge returned HTTP ' . $httpCode),
						'exitCode' => $httpCode,
				];
		}

		$headersText = implode("\r\n", $headers);
		$context = stream_context_create([
				'http' => [
						'method' => 'POST',
						'header' => $headersText . "\r\n",
						'content' => $payload,
						'timeout' => 20,
				],
		]);

		$raw = @file_get_contents($endpoint, false, $context);
		if (!is_string($raw)) {
				$lastError = error_get_last();
				$message = is_array($lastError) ? (string) ($lastError['message'] ?? '') : '';
				return [
						'ok' => false,
						'output' => '',
						'error' => 'Bridge request failed' . ($message !== '' ? ': ' . $message : '.'),
						'exitCode' => 126,
				];
		}

		$decoded = json_decode($raw, true);
		if (is_array($decoded)) {
				$decoded['ok'] = (bool) ($decoded['ok'] ?? false);
				$decoded['output'] = (string) ($decoded['output'] ?? '');
				$decoded['error'] = (string) ($decoded['error'] ?? '');
				$decoded['exitCode'] = (int) ($decoded['exitCode'] ?? 0);
				return $decoded;
		}

		return [
				'ok' => true,
				'output' => trim($raw),
				'error' => '',
				'exitCode' => 0,
		];
}

function runMimicChatPromptLocal(string $prompt, bool $autoApprove): array
{
		$binaryPath = resolveMimicBinaryPath();
		if ($binaryPath === '') {
				return ['ok' => false, 'output' => '', 'error' => 'Mimic binary not found. Set MIMIC_WEB_COMMAND or place trading-bot-v3 binary in windows/ or raspi/.', 'exitCode' => 127];
		}

		if (!isPhpFunctionCallable('proc_open')) {
				return runMimicChatPromptWithShell($binaryPath, $prompt, $autoApprove);
		}

		$command = [$binaryPath, '-cli', $prompt];
		$descriptorSpec = [
				0 => ['pipe', 'r'],
				1 => ['pipe', 'w'],
				2 => ['pipe', 'w'],
		];

		$pipes = [];
		$process = @proc_open($command, $descriptorSpec, $pipes, dirname($binaryPath));
		if (!is_resource($process)) {
				return ['ok' => false, 'output' => '', 'error' => 'Failed to start mimic process.', 'exitCode' => 126];
		}

		$stdinPayload = $autoApprove ? "y\n" : "n\n";
		fwrite($pipes[0], $stdinPayload);
		fclose($pipes[0]);

		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		$exitCode = proc_close($process);
		$output = trim((string) $stdout);
		$error = trim((string) $stderr);

		if ($output === '' && $error !== '') {
				$output = $error;
		}

		if ($exitCode !== 0 && $error === '') {
				$error = 'Mimic process exited with code ' . $exitCode;
		}

		return [
				'ok' => $exitCode === 0,
				'output' => $output,
				'error' => $error,
				'exitCode' => $exitCode,
		];
}

function runMimicChatPrompt(string $prompt): array
{
		$autoApprove = envBoolValue('MIMIC_WEB_AUTO_APPROVE', true);
		$bridgeEndpoint = trim((string) (getenv('MIMIC_WEB_ENDPOINT') ?: ''));
		if ($bridgeEndpoint === '') {
				return runMimicChatPromptLocal($prompt, $autoApprove);
		}

		$bridgeResult = runMimicChatPromptViaHttpBridge($bridgeEndpoint, $prompt, $autoApprove);
		if (($bridgeResult['ok'] ?? false) === true) {
				return $bridgeResult;
		}

		$bridgeError = trim((string) ($bridgeResult['error'] ?? ''));
		$bridgeExitCode = (int) ($bridgeResult['exitCode'] ?? 0);
		$isBridgeTransportFailure = $bridgeExitCode === 126
				|| stripos($bridgeError, 'connection refused') !== false
				|| stripos($bridgeError, 'failed to open stream') !== false
				|| stripos($bridgeError, 'could not connect') !== false
				|| stripos($bridgeError, 'timed out') !== false;

		if (!$isBridgeTransportFailure) {
				return $bridgeResult;
		}

		$fallback = runMimicChatPromptLocal($prompt, $autoApprove);
		if (($fallback['ok'] ?? false) === true) {
				return $fallback;
		}

		$fallbackError = trim((string) ($fallback['error'] ?? ''));
		$fallback['error'] = 'Bridge unavailable (' . ($bridgeError !== '' ? $bridgeError : 'transport failure') . ')'
				. ($fallbackError !== '' ? '; local fallback failed: ' . $fallbackError : '.');
		return $fallback;
}

function readJsonBody(): array
{
		$raw = file_get_contents('php://input');
		if (!is_string($raw) || trim($raw) === '') {
				return [];
		}

		$decoded = json_decode($raw, true);
		return is_array($decoded) ? $decoded : [];
}

loadEnvFile(__DIR__ . DIRECTORY_SEPARATOR . '.env');
if ((getenv('MIMIC_WEB_ENDPOINT') ?: '') === '') {
		loadEnvFile(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');
}
date_default_timezone_set('Asia/Jakarta');

if (($_GET['action'] ?? '') === 'mimic_chat') {
		header('Content-Type: application/json; charset=utf-8');

		try {
			if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
				http_response_code(405);
				echo json_encode(['ok' => false, 'error' => 'Method not allowed. Use POST.'], JSON_UNESCAPED_SLASHES);
				exit;
			}

			$body = readJsonBody();
			$prompt = trim((string) (($body['prompt'] ?? '') !== '' ? $body['prompt'] : ($_POST['prompt'] ?? '')));
			$maxPromptChars = max(20, min(1200, (int) envFloatValue('MIMIC_WEB_MAX_PROMPT_CHARS', 400)));
			if ($prompt === '') {
				http_response_code(422);
				echo json_encode(['ok' => false, 'error' => 'Prompt is required.'], JSON_UNESCAPED_SLASHES);
				exit;
			}

			if (strlen($prompt) > $maxPromptChars) {
				http_response_code(422);
				echo json_encode(['ok' => false, 'error' => 'Prompt exceeds max length of ' . $maxPromptChars . ' chars.'], JSON_UNESCAPED_SLASHES);
				exit;
			}

			$result = runMimicChatPrompt($prompt);
			if (($result['ok'] ?? false) !== true) {
				http_response_code(500);
			}

			echo json_encode($result, JSON_UNESCAPED_SLASHES);
		} catch (Throwable $exception) {
			http_response_code(500);
			echo json_encode([
				'ok' => false,
				'output' => '',
				'error' => 'Mimic chat backend error: ' . $exception->getMessage(),
				'exitCode' => 500,
			], JSON_UNESCAPED_SLASHES);
		}
		exit;
}

$pairOptions = [
		'btcidr' => 'BTC/IDR',
		'ethidr' => 'ETH/IDR',
		'dogeidr' => 'DOGE/IDR',
		'usdtidr' => 'USDT/IDR',
		'xautidr' => 'XAUT/IDR',
];

$configuredPairs = trim((string) (getenv('TRADING_PAIRS') ?: ''));
if ($configuredPairs !== '') {
		$normalized = [];
		foreach (explode(',', $configuredPairs) as $configuredPair) {
				$cleanPair = sanitizePair($configuredPair);
				if ($cleanPair === '') {
						continue;
				}
				$normalized[] = $cleanPair;
		}

		if (!empty($normalized)) {
				$pairOptions = [];
				foreach (array_unique($normalized) as $pairValue) {
						$symbol = strtoupper(substr($pairValue, 0, max(1, strlen($pairValue) - 3)));
						$quote = strtoupper(substr($pairValue, -3));
						$pairOptions[$pairValue] = $symbol . '/' . $quote;
				}
		}
}

$defaultPair = sanitizePair((string) (getenv('DASHBOARD_DEFAULT_PAIR') ?: 'btcidr'));
$pair = sanitizePair($_GET['pair'] ?? $defaultPair);
if (!array_key_exists($pair, $pairOptions)) {
		$pair = array_key_first($pairOptions) ?: 'btcidr';
}

$refreshSeconds = (int) (getenv('DASHBOARD_AUTO_REFRESH_SEC') ?: '15');
if ($refreshSeconds < 5 || $refreshSeconds > 120) {
		$refreshSeconds = 15;
}

$telegramChannelUrl = normalizeExternalBrowseUrl((string) (getenv('TELEGRAM_CHANNEL') ?: ''));
$selectedPortfolioInstance = sanitizeInstanceKeyFilter((string) ($_GET['portfolio_instance'] ?? ''));
$selectedPortfolioScope = sanitizePortfolioReportScope((string) ($_GET['portfolio_scope'] ?? 'all'));
$telegramChatStorePath = __DIR__ . DIRECTORY_SEPARATOR . 'telegram.json';
if (!is_file($telegramChatStorePath)) {
		$telegramChatStorePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'telegram.json';
}
$telegramChatStore = loadTelegramChatStoreData($telegramChatStorePath);
$telegramDisplayNames = is_array($telegramChatStore['displayNames'] ?? null) ? $telegramChatStore['displayNames'] : [];
$investmentCostModel = buildInvestmentCostModel();

$payload = buildDashboardPayload($pair);
$payload['runtimeConfig'] = buildV3RuntimeConfig($pair);
$advisorInstanceKey = $selectedPortfolioInstance;
$advisorScopeLabel = $advisorInstanceKey !== '' ? formatPortfolioInstanceLabel($advisorInstanceKey, $telegramDisplayNames) : 'All instances';
$payload['autoLearnAdvisor'] = fetchAutoLearnAdvisor(14, $advisorInstanceKey, (float) ($payload['runtimeConfig']['gridTakeProfitPct'] ?? 2.0));
$payload['autoLearnRecommendations'] = buildAutoLearnRecommendations($payload['autoLearnAdvisor'], $payload['runtimeConfig']);
$payload['autoLearnScopeLabel'] = $advisorScopeLabel;
$strategyInstanceKey = $selectedPortfolioInstance;
$payload['strategyState'] = fetchV3StrategyState($pair, (int) ($payload['runtimeConfig']['longMaPeriod'] ?? 200), $strategyInstanceKey);
$payload['marketTelemetry'] = buildV3MarketTelemetry($payload['summary'], $payload['orderbook'], $payload['runtimeConfig'], $payload['strategyState']['signal'] ?? []);
$payload['strategySummary'] = buildV3StrategySummary($pair, $payload['runtimeConfig'], $payload['strategyState'], $payload['marketTelemetry']);
$portfolioReport = fetchPortfolioReports(240, '', $selectedPortfolioScope);
$payload['investmentCostModel'] = $investmentCostModel;
$payload['portfolioReports'] = enrichPortfolioReportsWithCost($portfolioReport['rows'], $investmentCostModel);
$payload['selectedPortfolioInstance'] = $selectedPortfolioInstance;
$payload['selectedPortfolioScope'] = $selectedPortfolioScope;
$payload['portfolioReportsFiltered'] = filterPortfolioReportsByInstance($payload['portfolioReports'], $selectedPortfolioInstance);
$payload['portfolioReportsError'] = $portfolioReport['error'];
$payload['portfolioDbName'] = (string) ($portfolioReport['dbName'] ?? DEFAULT_TRADING_DB);
$payload['telegramDisplayNames'] = $telegramDisplayNames;
$payload['portfolioSummary'] = summarizePortfolioReports($payload['portfolioReports'], $portfolioReport['tradeWinStats'] ?? []);
$payload['portfolioSummaryFiltered'] = summarizePortfolioReports(
		$payload['portfolioReportsFiltered'],
		$selectedPortfolioInstance !== '' ? ($portfolioReport['tradeWinStatsByInstance'][$selectedPortfolioInstance] ?? []) : ($portfolioReport['tradeWinStats'] ?? [])
);
$payload['telegramPortfolioCards'] = buildTelegramPortfolioCards(
		$payload['portfolioReports'],
		is_array($telegramChatStore['chatIds'] ?? null) ? $telegramChatStore['chatIds'] : [],
		$telegramDisplayNames,
		$portfolioReport['tradeWinStatsByInstance'] ?? [],
		$selectedPortfolioInstance
);
$payload['refreshSeconds'] = $refreshSeconds;
$mimicBridgeEndpoint = trim((string) (getenv('MIMIC_WEB_ENDPOINT') ?: ''));
$payload['mimicChat'] = [
		'enabled' => $mimicBridgeEndpoint !== '' || resolveMimicBinaryPath() !== '',
		'maxPromptChars' => max(20, min(1200, (int) envFloatValue('MIMIC_WEB_MAX_PROMPT_CHARS', 400))),
		'autoApprove' => envBoolValue('MIMIC_WEB_AUTO_APPROVE', true),
];

if (($_GET['ajax'] ?? '0') === '1') {
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($payload, JSON_UNESCAPED_SLASHES);
		exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>Trading <?= htmlspecialchars(strtoupper(DASHBOARD_VERSION), ENT_QUOTES, 'UTF-8'); ?> Dashboard</title>
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
	<style>
		@import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=IBM+Plex+Mono:wght@400;500&display=swap');

		:root {
			--bg: #07121f;
			--bg-deep: #03090f;
			--panel: #0f2135cc;
			--panel-soft: #0a1829;
			--text: #e9f3ff;
			--muted: #91a5bc;
			--green: #22c55e;
			--red: #f87171;
			--amber: #f59e0b;
			--border: #285071;
			--accent: #22d3ee;
			--accent-2: #38bdf8;
		}

		* { box-sizing: border-box; }

		body {
			margin: 0;
			font-family: "Space Grotesk", "Segoe UI", sans-serif;
			color: var(--text);
			background:
				radial-gradient(circle at 10% -10%, #164e63 0, transparent 36%),
				radial-gradient(circle at 95% 0%, #1e3a8a 0, transparent 28%),
				linear-gradient(165deg, var(--bg) 0%, var(--bg-deep) 76%);
			min-height: 100vh;
			padding: 24px;
			animation: pageFade 480ms ease-out;
		}

		.container {
			max-width: 1280px;
			margin: 0 auto;
			display: grid;
			gap: 18px;
		}

		.card {
			background: linear-gradient(180deg, var(--panel), var(--panel-soft));
			border: 1px solid var(--border);
			border-radius: 16px;
			padding: 18px;
			box-shadow: 0 14px 40px rgba(0, 0, 0, 0.32);
			backdrop-filter: blur(8px);
		}

		.header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: 12px;
			flex-wrap: wrap;
		}

		h1 {
			margin: 0;
			font-size: 1.55rem;
			letter-spacing: 0.3px;
		}

		h3 {
			margin: 0 0 12px;
			font-size: 1.05rem;
		}

		.muted {
			color: var(--muted);
			font-size: 0.9rem;
		}

		.toolbar {
			display: flex;
			gap: 10px;
			align-items: center;
			flex-wrap: wrap;
		}

		select {
			background: #071321;
			color: var(--text);
			border: 1px solid var(--border);
			border-radius: 10px;
			padding: 10px 12px;
			min-width: 140px;
			font-family: "IBM Plex Mono", monospace;
		}

		button {
			background: linear-gradient(120deg, var(--accent), var(--accent-2));
			color: #001018;
			border: 0;
			border-radius: 10px;
			padding: 10px 14px;
			font-weight: 700;
			cursor: pointer;
			transition: transform 120ms ease, filter 120ms ease;
		}

		button:hover {
			transform: translateY(-1px);
			filter: brightness(1.05);
		}

		button.secondary {
			background: transparent;
			color: var(--text);
			border: 1px solid var(--border);
		}

		a.button-link {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			background: linear-gradient(120deg, var(--accent), var(--accent-2));
			color: #001018;
			border: 0;
			border-radius: 10px;
			padding: 10px 14px;
			font-weight: 700;
			text-decoration: none;
			transition: transform 120ms ease, filter 120ms ease;
		}

		a.button-link:hover {
			transform: translateY(-1px);
			filter: brightness(1.05);
		}

		.stats {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
			gap: 12px;
		}

		.stat {
			background: linear-gradient(180deg, #0c1a2b, #091423);
			border: 1px solid var(--border);
			border-radius: 12px;
			padding: 12px;
			animation: riseUp 360ms ease both;
		}

		.stat .label {
			color: var(--muted);
			font-size: 0.78rem;
			text-transform: uppercase;
			letter-spacing: 0.6px;
		}

		.stat .value {
			font-size: 1.08rem;
			margin-top: 4px;
			font-weight: 700;
			font-family: "IBM Plex Mono", monospace;
		}

		.green { color: var(--green); }
		.red { color: var(--red); }
		.amber { color: #fbbf24; }

		.agent-card {
			margin-top: 12px;
			border: 1px solid var(--border);
			border-radius: 12px;
			background: linear-gradient(180deg, #0a1a2e, #091626);
			padding: 14px;
		}

		.agent-title {
			font-size: 0.82rem;
			text-transform: uppercase;
			color: var(--muted);
			letter-spacing: 0.35px;
		}

		.agent-signal {
			margin-top: 5px;
			font-size: 1.15rem;
			font-weight: 800;
		}

		.agent-meta {
			margin-top: 6px;
			font-size: 0.88rem;
			color: var(--muted);
			font-family: "IBM Plex Mono", monospace;
		}

		.agent-reasons {
			margin: 8px 0 0;
			padding-left: 18px;
			color: #cbd5e1;
			font-size: 0.9rem;
		}

		.grid-two {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 18px;
		}

		.chart-wrap {
			position: relative;
			min-height: 300px;
		}

		.summary-grid {
			margin-top: 12px;
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
			gap: 10px;
		}

		.summary-chip {
			border: 1px solid #1f3b55;
			border-radius: 11px;
			padding: 10px;
			background: #081321;
		}

		.summary-chip .label {
			color: var(--muted);
			font-size: 0.74rem;
			text-transform: uppercase;
			letter-spacing: 0.5px;
		}

		.summary-chip .value {
			margin-top: 5px;
			font-family: "IBM Plex Mono", monospace;
			font-size: 1rem;
			font-weight: 600;
		}

		.summary-chip .subvalue {
			margin-top: 4px;
			font-size: 0.78rem;
			color: var(--muted);
		}

		a.summary-chip {
			display: block;
			color: inherit;
			text-decoration: none;
			transition: transform 120ms ease, border-color 120ms ease, box-shadow 120ms ease;
		}

		a.summary-chip:hover {
			transform: translateY(-1px);
			border-color: #4cc9f0;
			box-shadow: 0 10px 22px rgba(4, 18, 32, 0.35);
		}

		.summary-chip.is-active {
			border-color: var(--accent);
			box-shadow: inset 0 0 0 1px rgba(34, 211, 238, 0.35);
		}

		.telegram-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
			gap: 10px;
			margin-top: 14px;
		}

		.strategy-grid {
			display: grid;
			grid-template-columns: 1.15fr 0.85fr;
			gap: 18px;
		}

		.strategy-note {
			margin-top: 10px;
			padding: 11px 12px;
			border: 1px solid #1f3b55;
			border-radius: 12px;
			background: rgba(8, 19, 33, 0.78);
			font-size: 0.9rem;
			color: #d7e4f3;
		}

		.recommendation-list {
			display: grid;
			gap: 12px;
			margin-top: 14px;
		}

		.recommendation-item {
			border: 1px solid #1f3b55;
			border-radius: 12px;
			padding: 12px;
			background: rgba(8, 19, 33, 0.82);
		}

		.recommendation-item.is-profit {
			border-color: rgba(34, 197, 94, 0.45);
		}

		.recommendation-item.is-risk {
			border-color: rgba(245, 158, 11, 0.45);
		}

		.recommendation-item.is-hold {
			border-color: rgba(56, 189, 248, 0.4);
		}

		.recommendation-head {
			display: flex;
			justify-content: space-between;
			gap: 10px;
			align-items: flex-start;
			flex-wrap: wrap;
		}

		.recommendation-title {
			font-weight: 700;
			font-size: 0.95rem;
		}

		.recommendation-param {
			font-family: "IBM Plex Mono", monospace;
			font-size: 0.82rem;
			color: var(--accent);
		}

		.recommendation-body {
			margin-top: 8px;
			font-size: 0.9rem;
			color: #d7e4f3;
		}

		.recommendation-meta {
			margin-top: 8px;
			font-size: 0.82rem;
			color: var(--muted);
			font-family: "IBM Plex Mono", monospace;
		}

		.strategy-stack {
			display: grid;
			gap: 12px;
		}

		.flag-row {
			display: flex;
			flex-wrap: wrap;
			gap: 8px;
			margin-top: 10px;
		}

		.flag-chip {
			display: inline-flex;
			align-items: center;
			padding: 6px 10px;
			border-radius: 999px;
			font-size: 0.75rem;
			font-weight: 700;
			letter-spacing: 0.3px;
			text-transform: uppercase;
			border: 1px solid #26445f;
			background: #0a1a2c;
		}

		.flag-good { color: #bbf7d0; border-color: #166534; background: #052e16; }
		.flag-warn { color: #fde68a; border-color: #854d0e; background: #3f2b06; }
		.flag-bad { color: #fecaca; border-color: #991b1b; background: #3b0a0a; }

		table {
			width: 100%;
			border-collapse: collapse;
			font-size: 0.9rem;
			font-family: "IBM Plex Mono", monospace;
		}

		th,
		td {
			border-bottom: 1px solid #1f3b55;
			text-align: left;
			padding: 9px 7px;
		}

		th {
			color: var(--muted);
			font-weight: 500;
		}

		.pill {
			display: inline-block;
			padding: 4px 10px;
			border-radius: 999px;
			font-size: 0.76rem;
			font-weight: 700;
			text-transform: uppercase;
		}

		.pill-buy {
			color: #052e16;
			background: #22c55e;
		}

		.pill-sell {
			color: #450a0a;
			background: #ef4444;
		}

		.errors {
			border: 1px solid #7f1d1d;
			background: #450a0a66;
			color: #fecaca;
			border-radius: 12px;
			padding: 12px;
			margin-top: 10px;
			margin-bottom: 12px;
		}

		.chat-card {
			order: -4;
			border: 1px solid #2e5f80;
			background: linear-gradient(180deg, rgba(5, 18, 31, 0.92), rgba(7, 24, 38, 0.84));
		}

		.chat-form {
			display: grid;
			gap: 10px;
		}

		.chat-input {
			width: 100%;
			min-height: 84px;
			resize: vertical;
			background: #071321;
			color: var(--text);
			border: 1px solid var(--border);
			border-radius: 10px;
			padding: 10px 12px;
			font-family: "IBM Plex Mono", monospace;
			line-height: 1.45;
		}

		.chat-row {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 10px;
			flex-wrap: wrap;
		}

		.chat-toggle-row {
			margin-top: 10px;
		}

		.chat-result {
			margin-top: 10px;
			padding: 12px;
			border: 1px solid #1f3b55;
			border-radius: 10px;
			background: rgba(3, 12, 22, 0.82);
			font-family: "IBM Plex Mono", monospace;
			font-size: 0.86rem;
			line-height: 1.45;
			color: #dbeafe;
			white-space: pre-wrap;
			word-break: break-word;
		}

		.chat-result.error {
			border-color: #7f1d1d;
			color: #fecaca;
			background: rgba(69, 10, 10, 0.42);
		}

		.chat-history {
			margin-top: 10px;
			display: grid;
			gap: 8px;
		}

		.chat-history-item {
			border: 1px solid #1f3b55;
			border-radius: 10px;
			padding: 10px;
			background: rgba(4, 14, 24, 0.86);
		}

		.chat-history-item.error {
			border-color: #7f1d1d;
		}

		.chat-history-meta {
			font-size: 0.75rem;
			color: var(--muted);
			margin-bottom: 4px;
			font-family: "IBM Plex Mono", monospace;
		}

		.chat-history-prompt {
			font-size: 0.82rem;
			color: #bae6fd;
			margin-bottom: 5px;
			font-family: "IBM Plex Mono", monospace;
			white-space: pre-wrap;
		}

		.chat-history-reply {
			font-size: 0.82rem;
			color: #e2e8f0;
			font-family: "IBM Plex Mono", monospace;
			white-space: pre-wrap;
			word-break: break-word;
		}

		@keyframes pageFade {
			from { opacity: 0; transform: translateY(6px); }
			to { opacity: 1; transform: translateY(0); }
		}

		@keyframes riseUp {
			from { opacity: 0; transform: translateY(8px); }
			to { opacity: 1; transform: translateY(0); }
		}

		@media (max-width: 960px) {
			.grid-two {
				grid-template-columns: 1fr;
			}

			.strategy-grid {
				grid-template-columns: 1fr;
			}

			h1 {
				font-size: 1.35rem;
			}

			body {
				padding: 14px;
			}
		}

		.portfolio-card {
			order: -1;
		}

		.strategy-card {
			order: -2;
		}

		.auto-learn-card {
			order: -3;
		}

		.strategy-card.is-hidden {
			display: none;
		}

		.auto-learn-card.is-hidden,
		.portfolio-card.is-hidden {
			display: none;
		}
	</style>
</head>
<body>
	<div class="container">
		<div class="card">
			<div class="header">
				<div>
				<h1>Trading Bot <?= htmlspecialchars(strtoupper(DASHBOARD_VERSION), ENT_QUOTES, 'UTF-8'); ?> Console - <?= htmlspecialchars($pairOptions[$payload['pair']] ?? strtoupper($payload['pair']), ENT_QUOTES, 'UTF-8'); ?></h1>
				<div class="muted">Updated <?= htmlspecialchars($payload['updatedAt'], ENT_QUOTES, 'UTF-8'); ?> | Next auto refresh in <span id="refreshCountdown"><?= (int) $refreshSeconds; ?></span>s | Debug build 2026-03-19-portfolio-all-fix</div>
				</div>
				<form method="GET" class="toolbar">
					<label class="muted" for="pair">Pair:</label>
					<select id="pair" name="pair">
						<?php foreach ($pairOptions as $pairValue => $pairLabel): ?>
							<option value="<?= htmlspecialchars($pairValue, ENT_QUOTES, 'UTF-8'); ?>" <?= $payload['pair'] === $pairValue ? 'selected' : ''; ?>>
								<?= htmlspecialchars($pairLabel, ENT_QUOTES, 'UTF-8'); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<button type="submit">Load</button>
					<?php if ($telegramChannelUrl !== ''): ?>
						<a class="button-link" href="<?= htmlspecialchars($telegramChannelUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Telegram Channel</a>
					<?php endif; ?>
					<button type="button" class="secondary" id="toggleStrategyCard" aria-controls="strategyCard" aria-expanded="true">Hide Strategy</button>
				</form>
			</div>

			<?php if (!empty($payload['errors'])): ?>
				<div class="errors">
					<?php foreach ($payload['errors'] as $error): ?>
						<div><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="stats">
				<div class="stat"><div class="label">Last Price</div><div class="value amber">Rp <?= formatNumber((float) $payload['summary']['last']); ?></div></div>
				<div class="stat"><div class="label">Best Buy (Bid)</div><div class="value green">Rp <?= formatNumber((float) $payload['summary']['buyTop']); ?></div></div>
				<div class="stat"><div class="label">Best Sell (Ask)</div><div class="value red">Rp <?= formatNumber((float) $payload['summary']['sellTop']); ?></div></div>
				<div class="stat"><div class="label">Spread</div><div class="value">Rp <?= formatNumber((float) $payload['summary']['spread']); ?></div></div>
				<div class="stat"><div class="label">24h High / Low</div><div class="value">Rp <?= formatNumber((float) $payload['summary']['high']); ?> / <?= formatNumber((float) $payload['summary']['low']); ?></div></div>
				<div class="stat"><div class="label">Volume IDR</div><div class="value">Rp <?= formatNumber((float) $payload['summary']['volIdr']); ?></div></div>
			</div>

			<div class="agent-card">
				<div class="agent-title">Finance Agent Signal</div>
				<?php $agent = $payload['financeAgent'] ?? ['action' => 'HOLD', 'confidence' => 50, 'score' => 0, 'reasons' => []]; ?>
				<div class="agent-signal <?= ($agent['action'] ?? 'HOLD') === 'BUY' ? 'green' : ((($agent['action'] ?? 'HOLD') === 'SELL') ? 'red' : 'amber'); ?>">
					<?= htmlspecialchars((string) ($agent['action'] ?? 'HOLD'), ENT_QUOTES, 'UTF-8'); ?>
				</div>
				<div class="agent-meta">
					Confidence: <?= formatNumber((float) ($agent['confidence'] ?? 0)); ?>% |
					Score: <?= formatNumber((float) ($agent['score'] ?? 0)); ?> |
					Buys: <?= formatNumber((float) ($agent['buyTradeCount'] ?? 0)); ?> |
					Sells: <?= formatNumber((float) ($agent['sellTradeCount'] ?? 0)); ?>
				</div>
				<ul class="agent-reasons">
					<?php foreach (($agent['reasons'] ?? []) as $reason): ?>
						<li><?= htmlspecialchars((string) $reason, ENT_QUOTES, 'UTF-8'); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>

		<div class="card chat-card">
			<?php
				$mimicChat = is_array($payload['mimicChat'] ?? null) ? $payload['mimicChat'] : [];
				$mimicEnabled = (bool) ($mimicChat['enabled'] ?? false);
				$mimicPromptLimit = (int) ($mimicChat['maxPromptChars'] ?? 400);
				$mimicAutoApprove = (bool) ($mimicChat['autoApprove'] ?? true);
			?>
			<div class="header">
				<div>
					<h3>Mimic Chat Console</h3>
					<div class="muted">Natural language command parser for trading bot actions.</div>
				</div>
			</div>
			<?php if (!$mimicEnabled): ?>
				<div class="errors">Mimic runtime not detected. Configure <strong>MIMIC_WEB_COMMAND</strong> for local binary execution, or set <strong>MIMIC_WEB_ENDPOINT</strong> to use an HTTP bridge service.</div>
			<?php else: ?>
				<form id="mimicChatForm" class="chat-form">
					<textarea id="mimicPrompt" class="chat-input" maxlength="<?= $mimicPromptLimit; ?>" placeholder="Example: can you sell btc and eth pairs now?"></textarea>
					<div class="chat-row">
						<div class="muted">Max <?= $mimicPromptLimit; ?> chars | <?= $mimicAutoApprove ? 'Auto-approve enabled for this web action' : 'Auto-approve disabled (execution will be cancelled)'; ?></div>
						<div class="toolbar">
							<button type="button" class="secondary" id="mimicClearHistoryBtn">Clear History</button>
							<button type="submit" id="mimicSendBtn">Send to Mimic</button>
						</div>
					</div>
				</form>
				<div class="chat-toggle-row toolbar">
					<button type="button" class="secondary" id="toggleStrategyCardChat" aria-controls="strategyCard" aria-expanded="true">Hide V3 Strategy State</button>
					<button type="button" class="secondary" id="toggleAutoLearnCard" aria-controls="autoLearnCard" aria-expanded="true">Hide DB Auto-Learn Advisor</button>
					<button type="button" class="secondary" id="togglePortfolioCard" aria-controls="portfolioCard" aria-expanded="true">Hide Portfolio Daily Report</button>
				</div>
				<div id="mimicResult" class="chat-result" aria-live="polite">Ready.</div>
				<div id="mimicHistory" class="chat-history" aria-live="polite"></div>
			<?php endif; ?>
		</div>

		<div class="card strategy-card" id="strategyCard">
			<?php
				$strategyState = $payload['strategyState'] ?? [];
				$strategySignal = $strategyState['signal'] ?? [];
				$strategyPosition = $strategyState['position'] ?? [];
				$strategySummary = $payload['strategySummary'] ?? [];
				$runtimeConfig = $payload['runtimeConfig'] ?? [];
				$marketTelemetry = $payload['marketTelemetry'] ?? [];
				$instanceLabel = (string) (($strategySummary['instanceKey'] ?? '') !== '' ? $strategySummary['instanceKey'] : 'latest available');
				$regime = (string) ($strategySignal['regime'] ?? 'N/A');
				$reasonText = trim((string) ($strategySignal['reason'] ?? ''));
				$priceHistoryCount = (int) ($strategyState['priceHistoryCount'] ?? 0);
				$maPeriod = (int) ($runtimeConfig['maPeriod'] ?? 50);
				$longMaPeriod = (int) ($runtimeConfig['longMaPeriod'] ?? 200);
				$maReady = ((float) ($strategySignal['maValue'] ?? 0)) > 0;
				$longMaReady = ((float) ($strategySignal['longMaValue'] ?? 0)) > 0;
				$maStatusText = $maReady ? ('Rp ' . formatNumber((float) ($strategySignal['maValue'] ?? 0), 0)) : 'Warming Up';
				$longMaStatusText = $longMaReady ? ('Rp ' . formatNumber((float) ($strategySignal['longMaValue'] ?? 0), 0)) : 'Warming Up';
			?>
			<div class="header">
				<div>
					<h3>V3 Strategy State</h3>
					<div class="muted">Instance <?= htmlspecialchars($instanceLabel, ENT_QUOTES, 'UTF-8'); ?> | DB <?= htmlspecialchars((string) ($strategyState['dbName'] ?? $payload['portfolioDbName']), ENT_QUOTES, 'UTF-8'); ?></div>
				</div>
			</div>

			<?php if (!empty($strategyState['error'])): ?>
				<div class="errors"><?= htmlspecialchars((string) $strategyState['error'], ENT_QUOTES, 'UTF-8'); ?></div>
			<?php endif; ?>

			<div class="strategy-grid">
				<div class="strategy-stack">
					<div class="summary-grid">
						<div class="summary-chip">
							<div class="label">Regime</div>
							<div class="value <?= $regime === 'BULL' ? 'green' : ($regime === 'CRASH' ? 'red' : 'amber'); ?>"><?= htmlspecialchars($regime, ENT_QUOTES, 'UTF-8'); ?></div>
							<div class="subvalue">Latest signal <?= htmlspecialchars((string) ($strategySignal['createdAt'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
						</div>
						<div class="summary-chip">
							<div class="label">MA / Long MA</div>
							<div class="value"><?= htmlspecialchars($maStatusText, ENT_QUOTES, 'UTF-8'); ?> / <?= htmlspecialchars($longMaStatusText, ENT_QUOTES, 'UTF-8'); ?></div>
							<div class="subvalue">History <?= $priceHistoryCount; ?>/<?= $maPeriod; ?> for MA, <?= $priceHistoryCount; ?>/<?= $longMaPeriod; ?> for long MA | Trend strength <?= formatNumber((float) ($marketTelemetry['trendStrengthPct'] ?? 0), 2); ?>%</div>
						</div>
						<div class="summary-chip">
							<div class="label">ATR</div>
							<div class="value">Rp <?= formatNumber((float) ($strategySignal['atrValue'] ?? 0), 0); ?></div>
							<div class="subvalue">ATR x <?= formatNumber((float) ($runtimeConfig['atrMultiplier'] ?? 0), 2); ?> | Grid x <?= formatNumber((float) ($runtimeConfig['atrGridMultiplier'] ?? 0), 2); ?></div>
						</div>
						<div class="summary-chip">
							<div class="label">Position / Allocation</div>
							<div class="value">Rp <?= formatNumber((float) ($strategySummary['positionValueIdr'] ?? 0), 0); ?></div>
							<div class="subvalue">Qty <?= formatNumber((float) ($strategyPosition['quantity'] ?? 0), 8); ?> | <?= formatNumber((float) ($strategySummary['allocationPct'] ?? 0), 2); ?>% of portfolio</div>
						</div>
						<div class="summary-chip">
							<div class="label">Grid / Harvest</div>
							<div class="value">Level <?= (int) ($strategyPosition['gridLevel'] ?? 0); ?> / <?= (int) ($strategySummary['gridSlotsMax'] ?? 0); ?></div>
							<div class="subvalue">Harvest stage <?= (int) ($strategySummary['harvestStage'] ?? 0); ?> | Levels <?= htmlspecialchars(formatCompactFloatList((array) ($runtimeConfig['harvestLevelsPct'] ?? [])), ENT_QUOTES, 'UTF-8'); ?>%</div>
						</div>
						<div class="summary-chip">
							<div class="label">Maker Engine</div>
							<div class="value <?= (($marketTelemetry['makerMode'] ?? '') === 'TREND PAUSED') ? 'amber' : 'green'; ?>"><?= htmlspecialchars((string) ($marketTelemetry['makerMode'] ?? 'OFF'), ENT_QUOTES, 'UTF-8'); ?></div>
							<div class="subvalue">Open buy/sell <?= (int) ($strategySummary['makerBuyOpen'] ?? 0); ?>/<?= (int) ($strategySummary['makerSellOpen'] ?? 0); ?> | Refresh <?= formatNumber((float) ($runtimeConfig['makerRefreshSec'] ?? 0), 0); ?>s</div>
						</div>
					</div>

					<div class="strategy-note">
						<?= htmlspecialchars($reasonText !== '' ? $reasonText : 'No stored v3 signal reason yet for this pair.', ENT_QUOTES, 'UTF-8'); ?>
					</div>
				</div>

				<div class="strategy-stack">
					<div class="summary-grid">
						<div class="summary-chip">
							<div class="label">Top 5 Bid / Ask Depth</div>
							<div class="value">Rp <?= formatNumber((float) ($marketTelemetry['top5BidDepthIdr'] ?? 0), 0); ?> / <?= formatNumber((float) ($marketTelemetry['top5AskDepthIdr'] ?? 0), 0); ?></div>
							<div class="subvalue">Liquidity floor Rp <?= formatNumber((float) ($runtimeConfig['minTop5BidDepthIdr'] ?? 0), 0); ?></div>
						</div>
						<div class="summary-chip">
							<div class="label">Orderbook Imbalance</div>
							<div class="value <?= ((float) ($marketTelemetry['orderbookImbalancePct'] ?? 0)) >= 0 ? 'green' : 'red'; ?>"><?= formatNumber((float) ($marketTelemetry['orderbookImbalancePct'] ?? 0), 2); ?>%</div>
							<div class="subvalue">Threshold <?= formatNumber((float) ($runtimeConfig['imbalanceThresholdPct'] ?? 0), 1); ?>%</div>
						</div>
						<div class="summary-chip">
							<div class="label">Whale Wall Ratio</div>
							<div class="value">Sell <?= formatNumber((float) ($marketTelemetry['whaleSellWallRatio'] ?? 0), 2); ?> / Buy <?= formatNumber((float) ($marketTelemetry['whaleBuyWallRatio'] ?? 0), 2); ?></div>
							<div class="subvalue">Trigger <?= formatNumber((float) ($runtimeConfig['whaleWallRatio'] ?? 0), 2); ?> for <?= formatNumber((float) ($runtimeConfig['whaleConfirmSec'] ?? 0), 0); ?>s</div>
						</div>
						<div class="summary-chip">
							<div class="label">Daily Trades</div>
							<div class="value"><?= formatNumber((float) ($strategySummary['dailyTradeCount'] ?? 0), 0); ?> / <?= formatNumber((float) ($strategySummary['dailyTradeCap'] ?? 0), 0); ?></div>
							<div class="subvalue">Allocation cap <?= formatNumber((float) ($runtimeConfig['pairAllocationPct'] ?? 0), 1); ?>% of target pair, global max <?= formatNumber((float) ($runtimeConfig['maxPairAllocationPct'] ?? 0), 1); ?>%</div>
						</div>
					</div>

					<div class="flag-row">
						<span class="flag-chip <?= (($marketTelemetry['liquidityFilterPass'] ?? false) || !($runtimeConfig['liquidityFilterEnabled'] ?? false)) ? 'flag-good' : 'flag-bad'; ?>">Liquidity <?= ($runtimeConfig['liquidityFilterEnabled'] ?? false) ? (($marketTelemetry['liquidityFilterPass'] ?? false) ? 'PASS' : 'BLOCK') : 'OFF'; ?></span>
						<span class="flag-chip <?= !($marketTelemetry['imbalanceAlert'] ?? false) ? 'flag-good' : 'flag-warn'; ?>">Imbalance <?= ($runtimeConfig['imbalanceEnabled'] ?? false) ? (($marketTelemetry['imbalanceAlert'] ?? false) ? 'HOT' : 'NORMAL') : 'OFF'; ?></span>
						<span class="flag-chip <?= !($marketTelemetry['whaleAlert'] ?? false) ? 'flag-good' : 'flag-warn'; ?>">Whale <?= ($runtimeConfig['whaleEnabled'] ?? false) ? (($marketTelemetry['whaleAlert'] ?? false) ? 'ALERT' : 'CLEAR') : 'OFF'; ?></span>
						<span class="flag-chip <?= ($runtimeConfig['rebalanceEnabled'] ?? false) ? 'flag-good' : 'flag-warn'; ?>">Rebalance <?= ($runtimeConfig['rebalanceEnabled'] ?? false) ? ('ON ' . formatNumber((float) ($runtimeConfig['rebalanceIntervalHours'] ?? 0), 0) . 'H') : 'OFF'; ?></span>
						<span class="flag-chip <?= ($runtimeConfig['harvestEnabled'] ?? false) ? 'flag-good' : 'flag-warn'; ?>">Harvest <?= ($runtimeConfig['harvestEnabled'] ?? false) ? 'ON' : 'OFF'; ?></span>
						<span class="flag-chip <?= ($runtimeConfig['makerEnabled'] ?? false) ? 'flag-good' : 'flag-warn'; ?>">Maker <?= ($runtimeConfig['makerEnabled'] ?? false) ? (($runtimeConfig['makerLiveEnabled'] ?? false) ? 'LIVE' : 'PAPER') : 'OFF'; ?></span>
					</div>

					<div class="strategy-note">
						Grid levels <?= htmlspecialchars(formatCompactFloatList((array) ($runtimeConfig['gridLevels'] ?? [])), ENT_QUOTES, 'UTF-8'); ?>% | Maker levels <?= htmlspecialchars(formatCompactFloatList((array) ($runtimeConfig['makerLevelsPct'] ?? [])), ENT_QUOTES, 'UTF-8'); ?>% | MA buffer <?= formatNumber((float) ($runtimeConfig['maBufferPct'] ?? 0), 2); ?>%
					</div>
				</div>
			</div>
		</div>

		<div class="card auto-learn-card" id="autoLearnCard">
			<?php
				$autoLearnAdvisor = is_array($payload['autoLearnAdvisor'] ?? null) ? $payload['autoLearnAdvisor'] : [];
				$autoLearnRecommendations = is_array($payload['autoLearnRecommendations'] ?? null) ? $payload['autoLearnRecommendations'] : [];
				$autoLearnScopeLabel = (string) ($payload['autoLearnScopeLabel'] ?? 'All instances');
			?>
			<div class="header">
				<div>
					<h3>DB Auto-Learn Advisor</h3>
					<div class="muted">Scope <?= htmlspecialchars($autoLearnScopeLabel, ENT_QUOTES, 'UTF-8'); ?> | Advisory only, no bot parameters changed yet</div>
				</div>
			</div>

			<?php if (!empty($autoLearnAdvisor['error'])): ?>
				<div class="errors"><?= htmlspecialchars((string) $autoLearnAdvisor['error'], ENT_QUOTES, 'UTF-8'); ?></div>
			<?php else: ?>
				<div class="summary-grid">
					<div class="summary-chip">
						<div class="label">Lookback</div>
						<div class="value"><?= formatNumber((float) ($autoLearnAdvisor['lookbackDays'] ?? 14), 0); ?> days</div>
					</div>
					<div class="summary-chip">
						<div class="label">Buy / Sell Count</div>
						<div class="value"><?= formatNumber((float) ($autoLearnAdvisor['buyCount'] ?? 0), 0); ?> / <?= formatNumber((float) ($autoLearnAdvisor['sellCount'] ?? 0), 0); ?></div>
						<div class="subvalue">Sell win rate <?= formatNumber((float) ($autoLearnAdvisor['winRate'] ?? 0), 2); ?>%</div>
					</div>
					<div class="summary-chip">
						<div class="label">Realized Total</div>
						<div class="value <?= ((float) ($autoLearnAdvisor['realizedTotalIdr'] ?? 0)) >= 0 ? 'green' : 'red'; ?>">Rp <?= formatNumber((float) ($autoLearnAdvisor['realizedTotalIdr'] ?? 0), 0); ?></div>
						<div class="subvalue">Best / worst sell Rp <?= formatNumber((float) ($autoLearnAdvisor['bestSellIdr'] ?? 0), 0); ?> / Rp <?= formatNumber((float) ($autoLearnAdvisor['worstSellIdr'] ?? 0), 0); ?></div>
					</div>
					<div class="summary-chip">
						<div class="label">Report Days</div>
						<div class="value"><?= formatNumber((float) ($autoLearnAdvisor['reportDays'] ?? 0), 0); ?></div>
						<div class="subvalue">Positive / negative <?= formatNumber((float) ($autoLearnAdvisor['positiveDays'] ?? 0), 0); ?> / <?= formatNumber((float) ($autoLearnAdvisor['negativeDays'] ?? 0), 0); ?></div>
					</div>
					<div class="summary-chip">
						<div class="label">Open Positions</div>
						<div class="value"><?= formatNumber((float) ($autoLearnAdvisor['openPositions'] ?? 0), 0); ?></div>
						<div class="subvalue">Near profit target <?= formatNumber((float) ($autoLearnAdvisor['nearTargetOpenPositions'] ?? 0), 0); ?> | Underwater <?= formatNumber((float) ($autoLearnAdvisor['underwaterOpenPositions'] ?? 0), 0); ?></div>
					</div>
					<div class="summary-chip">
						<div class="label">Open PnL Spread</div>
						<div class="value"><?= formatNumber((float) ($autoLearnAdvisor['bestOpenProfitPct'] ?? 0), 3); ?>% / <?= formatNumber((float) ($autoLearnAdvisor['worstOpenProfitPct'] ?? 0), 3); ?>%</div>
						<div class="subvalue">Average open profit <?= formatNumber((float) ($autoLearnAdvisor['avgOpenProfitPct'] ?? 0), 3); ?>%</div>
					</div>
				</div>

				<div class="recommendation-list">
					<?php foreach ($autoLearnRecommendations as $recommendation): ?>
						<?php $priority = (string) ($recommendation['priority'] ?? 'hold'); ?>
						<div class="recommendation-item is-<?= htmlspecialchars($priority, ENT_QUOTES, 'UTF-8'); ?>">
							<div class="recommendation-head">
								<div>
									<div class="recommendation-title"><?= htmlspecialchars((string) ($recommendation['title'] ?? 'Recommendation'), ENT_QUOTES, 'UTF-8'); ?></div>
									<div class="recommendation-param"><?= htmlspecialchars((string) ($recommendation['parameter'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
								</div>
								<div class="recommendation-meta"><?= htmlspecialchars((string) (($recommendation['current'] ?? '') . ' -> ' . ($recommendation['suggested'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
							</div>
							<div class="recommendation-body"><?= htmlspecialchars((string) ($recommendation['reason'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
							<div class="recommendation-meta"><?= htmlspecialchars((string) ($recommendation['companion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="grid-two">
			<div class="card">
				<h3>Recent Trade Price Chart</h3>
				<div class="chart-wrap"><canvas id="tradeChart"></canvas></div>
			</div>
			<div class="card">
				<h3>Order Book Chart (Buy vs Sell)</h3>
				<div class="chart-wrap"><canvas id="orderbookChart"></canvas></div>
			</div>
		</div>

		<div class="card">
			<h3>Latest Buy / Sell Trades</h3>
			<table>
				<thead>
					<tr>
						<th>Time</th>
						<th>Type</th>
						<th>Price</th>
						<th>Amount</th>
						<th>TID</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach (array_slice($payload['trades'], 0, 25) as $trade): ?>
					<tr>
						<td><?= htmlspecialchars((string) $trade['timeLabel'], ENT_QUOTES, 'UTF-8'); ?></td>
						<td>
							<?php if (($trade['type'] ?? '') === 'buy'): ?>
								<span class="pill pill-buy">BUY</span>
							<?php else: ?>
								<span class="pill pill-sell">SELL</span>
							<?php endif; ?>
						</td>
						<td>Rp <?= formatNumber((float) $trade['price']); ?></td>
						<td><?= formatNumber((float) $trade['amount'], 8); ?></td>
						<td><?= htmlspecialchars((string) $trade['tid'], ENT_QUOTES, 'UTF-8'); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="card portfolio-card" id="portfolioCard">
			<h3>Portfolio Daily Report (<?= htmlspecialchars((string) ($payload['portfolioDbName'] ?? DEFAULT_TRADING_DB), ENT_QUOTES, 'UTF-8'); ?>)</h3>
			<?php if (!empty($payload['portfolioReportsError'])): ?>
				<div class="errors"><?= htmlspecialchars((string) $payload['portfolioReportsError'], ENT_QUOTES, 'UTF-8'); ?></div>
			<?php endif; ?>

			<?php
				$portfolioSummary = $payload['portfolioSummaryFiltered'] ?? [];
				$selectedPortfolioInstance = (string) ($payload['selectedPortfolioInstance'] ?? '');
				$selectedPortfolioScope = (string) ($payload['selectedPortfolioScope'] ?? 'all');
				$telegramDisplayNames = is_array($payload['telegramDisplayNames'] ?? null) ? $payload['telegramDisplayNames'] : [];
			?>
			<div class="header" style="margin-bottom:12px;">
				<div class="muted">
					<?= ($selectedPortfolioScope === 'today' ? 'Showing today only' : 'Showing all dates') . ($selectedPortfolioInstance !== '' ? (' | Filtered to ' . htmlspecialchars(formatPortfolioInstanceLabel($selectedPortfolioInstance, $telegramDisplayNames), ENT_QUOTES, 'UTF-8')) : ' | All portfolio instances'); ?>
				</div>
				<div class="toolbar">
					<a class="button-link<?= $selectedPortfolioScope === 'all' ? '' : ' secondary'; ?>" href="<?= htmlspecialchars(buildPortfolioFilterUrl($pair, $selectedPortfolioInstance, 'all'), ENT_QUOTES, 'UTF-8'); ?>">All Dates</a>
					<a class="button-link<?= $selectedPortfolioScope === 'today' ? '' : ' secondary'; ?>" href="<?= htmlspecialchars(buildPortfolioFilterUrl($pair, $selectedPortfolioInstance, 'today'), ENT_QUOTES, 'UTF-8'); ?>">Today</a>
					<?php if ($selectedPortfolioInstance !== ''): ?>
						<a class="button-link secondary" href="<?= htmlspecialchars(buildPortfolioFilterUrl($pair, '', $selectedPortfolioScope), ENT_QUOTES, 'UTF-8'); ?>">Show All Instances</a>
					<?php endif; ?>
				</div>
			</div>
			<div class="summary-grid">
				<div class="summary-chip">
					<div class="label">Rows Loaded</div>
					<div class="value"><?= formatNumber((float) ($portfolioSummary['reportCount'] ?? 0)); ?></div>
				</div>
				<div class="summary-chip">
					<div class="label">Total Opening</div>
					<div class="value">Rp <?= formatNumber((float) ($portfolioSummary['totalOpening'] ?? 0)); ?></div>
					<div class="subvalue">Last pre-day snapshot, fallback to first same-day snapshot</div>
				</div>
				<div class="summary-chip">
					<div class="label">Total Closing</div>
					<div class="value">Rp <?= formatNumber((float) ($portfolioSummary['totalClosing'] ?? 0)); ?></div>
				</div>
				<div class="summary-chip">
					<div class="label">Total PnL</div>
					<div class="value <?= ((float) ($portfolioSummary['totalPnl'] ?? 0)) >= 0 ? 'green' : 'red'; ?>">Rp <?= formatNumber((float) ($portfolioSummary['totalPnl'] ?? 0)); ?></div>
				</div>
				<div class="summary-chip">
					<div class="label">Total PnL %</div>
					<div class="value <?= ((float) ($portfolioSummary['totalPnlPct'] ?? 0)) >= 0 ? 'green' : 'red'; ?>"><?= formatNumber((float) ($portfolioSummary['totalPnlPct'] ?? 0), 3); ?>%</div>
				</div>
				<div class="summary-chip">
					<div class="label">Total Realized</div>
					<div class="value <?= ((float) ($portfolioSummary['totalRealized'] ?? 0)) >= 0 ? 'green' : 'red'; ?>">Rp <?= formatNumber((float) ($portfolioSummary['totalRealized'] ?? 0)); ?></div>
				</div>
				<div class="summary-chip">
					<div class="label">Total Operating Cost</div>
					<div class="value">Rp <?= formatNumber((float) ($portfolioSummary['totalCost'] ?? 0)); ?></div>
					<div class="subvalue">HW Rp <?= formatNumber((float) ($portfolioSummary['totalHardwareCost'] ?? 0)); ?> | Electric Rp <?= formatNumber((float) ($portfolioSummary['totalElectricityCost'] ?? 0)); ?></div>
				</div>
				<div class="summary-chip">
					<div class="label">Net After Cost</div>
					<div class="value <?= ((float) ($portfolioSummary['totalNetAfterCost'] ?? 0)) >= 0 ? 'green' : 'red'; ?>">Rp <?= formatNumber((float) ($portfolioSummary['totalNetAfterCost'] ?? 0)); ?></div>
					<div class="subvalue"><?= formatNumber((float) ($portfolioSummary['netAfterCostPct'] ?? 0), 3); ?>% of opening basis</div>
				</div>
				<div class="summary-chip">
					<div class="label">Avg Prediction Profit Rate</div>
					<div class="value"><?= formatNumber((float) ($portfolioSummary['avgPredictedProfitRatePct'] ?? 0), 4); ?>%</div>
					<div class="subvalue">Required daily rate to cover cost (varies by opening value)</div>
				</div>
				<div class="summary-chip">
					<div class="label">Cost Coverage</div>
					<div class="value <?= ((float) ($portfolioSummary['costCoveragePct'] ?? 0)) >= 100 ? 'green' : 'amber'; ?>"><?= formatNumber((float) ($portfolioSummary['costCoveragePct'] ?? 0), 2); ?>%</div>
					<div class="subvalue">Days net positive after cost <?= formatNumber((float) ($portfolioSummary['profitableAfterCostDays'] ?? 0)); ?></div>
				</div>
				<div class="summary-chip">
					<div class="label">Sell Win Rate</div>
					<div class="value"><?= htmlspecialchars(formatSellWinRateSummary($portfolioSummary), ENT_QUOTES, 'UTF-8'); ?></div>
				</div>
				<div class="summary-chip">
					<div class="label">Total Trades</div>
					<div class="value"><?= formatNumber((float) ($portfolioSummary['totalTrades'] ?? 0)); ?></div>
				</div>
				<div class="summary-chip">
					<div class="label">Latest Report Date</div>
					<div class="value"><?= htmlspecialchars((string) (($portfolioSummary['latestDate'] ?? '') !== '' ? $portfolioSummary['latestDate'] : '-'), ENT_QUOTES, 'UTF-8'); ?></div>
				</div>
			</div>

			<?php $telegramPortfolioCards = is_array($payload['telegramPortfolioCards'] ?? null) ? $payload['telegramPortfolioCards'] : []; ?>
			<?php if ($telegramPortfolioCards !== []): ?>
				<div class="telegram-grid">
					<a class="summary-chip <?= $selectedPortfolioInstance === '' ? 'is-active' : ''; ?>" href="<?= htmlspecialchars(buildPortfolioFilterUrl($pair, '', $selectedPortfolioScope), ENT_QUOTES, 'UTF-8'); ?>">
						<div class="label">All Instances</div>
						<div class="value">Rp <?= formatNumber((float) ($payload['portfolioSummary']['totalClosing'] ?? 0), 0); ?></div>
						<div class="subvalue">Rows <?= formatNumber((float) ($payload['portfolioSummary']['reportCount'] ?? 0), 0); ?> | <?= htmlspecialchars(formatSellWinRateSummary((array) ($payload['portfolioSummary'] ?? [])), ENT_QUOTES, 'UTF-8'); ?></div>
					</a>
					<?php foreach ($telegramPortfolioCards as $telegramCard): ?>
						<?php
							$telegramTotalPnl = (float) ($telegramCard['totalPnl'] ?? 0.0);
							$telegramClosingValue = (float) ($telegramCard['latestClosingValueIdr'] ?? 0.0);
							$telegramHasReports = (bool) ($telegramCard['hasReports'] ?? false);
							$telegramWinRate = (float) ($telegramCard['winRate'] ?? 0.0);
						?>
						<a class="summary-chip <?= ($telegramCard['isSelected'] ?? false) ? 'is-active' : ''; ?>" href="<?= htmlspecialchars(buildPortfolioFilterUrl($pair, (string) ($telegramCard['instanceKey'] ?? ''), $selectedPortfolioScope), ENT_QUOTES, 'UTF-8'); ?>">
							<div class="label"><?= htmlspecialchars((string) ($telegramCard['displayName'] ?? ($telegramCard['instanceKey'] ?? '-')), ENT_QUOTES, 'UTF-8'); ?></div>
							<div class="value <?= $telegramHasReports && $telegramTotalPnl < 0 ? 'red' : 'green'; ?>">Rp <?= formatNumber($telegramClosingValue, 0); ?></div>
							<div class="subvalue">
								<?php if ($telegramHasReports): ?>
									PnL Rp <?= formatNumber($telegramTotalPnl, 0); ?> | Trades <?= formatNumber((float) ($telegramCard['totalTrades'] ?? 0), 0); ?> | <?= htmlspecialchars(formatSellWinRateSummary($telegramCard), ENT_QUOTES, 'UTF-8'); ?>
								<?php else: ?>
									No portfolio reports yet for this Telegram instance
								<?php endif; ?>
							</div>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<table>
				<thead>
					<tr>
						<th>Instance</th>
						<th>Date</th>
						<th>Opening Basis</th>
						<th>Closing</th>
						<th>PnL</th>
						<th>PnL %</th>
						<th>Cost / Day</th>
						<th>Net After Cost</th>
						<th>Prediction Profit Rate</th>
						<th>Cost Coverage</th>
						<th>Realized</th>
						<th>Trades</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach (($payload['portfolioReportsFiltered'] ?? []) as $report): ?>
					<?php $pnlValue = (float) ($report['pnl_idr'] ?? 0); ?>
					<?php $costValue = (float) ($report['daily_cost_idr'] ?? 0); ?>
					<?php $netAfterCostValue = (float) ($report['net_after_cost_idr'] ?? 0); ?>
					<tr>
						<td><?= htmlspecialchars(formatPortfolioInstanceLabel((string) ($report['instance_key'] ?? '-'), $telegramDisplayNames), ENT_QUOTES, 'UTF-8'); ?></td>
						<td><?= htmlspecialchars((string) ($report['report_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
						<td>Rp <?= formatNumber((float) ($report['opening_value_idr'] ?? 0)); ?></td>
						<td>Rp <?= formatNumber((float) ($report['closing_value_idr'] ?? 0)); ?></td>
						<td class="<?= $pnlValue >= 0 ? 'green' : 'red'; ?>">Rp <?= formatNumber($pnlValue); ?></td>
						<td class="<?= ((float) ($report['pnl_pct'] ?? 0)) >= 0 ? 'green' : 'red'; ?>"><?= formatNumber((float) ($report['pnl_pct'] ?? 0), 3); ?>%</td>
						<td>Rp <?= formatNumber($costValue); ?></td>
						<td class="<?= $netAfterCostValue >= 0 ? 'green' : 'red'; ?>">Rp <?= formatNumber($netAfterCostValue); ?></td>
						<td><?= formatNumber((float) ($report['predicted_profit_rate_pct'] ?? 0), 4); ?>%</td>
						<td class="<?= ((float) ($report['cost_coverage_pct'] ?? 0)) >= 100 ? 'green' : 'amber'; ?>"><?= formatNumber((float) ($report['cost_coverage_pct'] ?? 0), 2); ?>%</td>
						<td>Rp <?= formatNumber((float) ($report['realized_pnl_idr'] ?? 0)); ?></td>
						<td><?= formatNumber((float) ($report['trades_count'] ?? 0)); ?></td>
					</tr>
				<?php endforeach; ?>
				<?php if (empty($payload['portfolioReportsFiltered'])): ?>
					<tr>
						<td colspan="12" class="muted"><?= $selectedPortfolioInstance !== '' ? ('No portfolio report rows for ' . htmlspecialchars(formatPortfolioInstanceLabel($selectedPortfolioInstance, $telegramDisplayNames), ENT_QUOTES, 'UTF-8') . ' in the selected date scope.') : 'No portfolio report rows in the selected date scope.'; ?></td>
					</tr>
				<?php endif; ?>
				</tbody>
				<?php if (!empty($payload['portfolioReportsFiltered'])): ?>
					<tfoot>
						<tr>
							<th>Total</th>
							<th><?= htmlspecialchars((string) (($portfolioSummary['latestDate'] ?? '') !== '' ? $portfolioSummary['latestDate'] : 'All Dates'), ENT_QUOTES, 'UTF-8'); ?></th>
							<th>Rp <?= formatNumber((float) ($portfolioSummary['totalOpening'] ?? 0)); ?></th>
							<th>Rp <?= formatNumber((float) ($portfolioSummary['totalClosing'] ?? 0)); ?></th>
							<th class="<?= ((float) ($portfolioSummary['totalPnl'] ?? 0)) >= 0 ? 'green' : 'red'; ?>">Rp <?= formatNumber((float) ($portfolioSummary['totalPnl'] ?? 0)); ?></th>
							<th class="<?= ((float) ($portfolioSummary['totalPnlPct'] ?? 0)) >= 0 ? 'green' : 'red'; ?>"><?= formatNumber((float) ($portfolioSummary['totalPnlPct'] ?? 0), 3); ?>%</th>
							<th>Rp <?= formatNumber((float) ($portfolioSummary['totalCost'] ?? 0)); ?></th>
							<th class="<?= ((float) ($portfolioSummary['totalNetAfterCost'] ?? 0)) >= 0 ? 'green' : 'red'; ?>">Rp <?= formatNumber((float) ($portfolioSummary['totalNetAfterCost'] ?? 0)); ?></th>
							<th><?= formatNumber((float) ($portfolioSummary['avgPredictedProfitRatePct'] ?? 0), 4); ?>%</th>
							<th class="<?= ((float) ($portfolioSummary['costCoveragePct'] ?? 0)) >= 100 ? 'green' : 'amber'; ?>"><?= formatNumber((float) ($portfolioSummary['costCoveragePct'] ?? 0), 2); ?>%</th>
							<th>Rp <?= formatNumber((float) ($portfolioSummary['totalRealized'] ?? 0)); ?></th>
							<th><?= formatNumber((float) ($portfolioSummary['totalTrades'] ?? 0)); ?></th>
						</tr>
					</tfoot>
				<?php endif; ?>
			</table>
			<?php $investmentCost = is_array($payload['investmentCostModel'] ?? null) ? $payload['investmentCostModel'] : []; ?>
			<div class="muted" style="margin-top:10px;">
				Cost model: Raspberry Pi 5 8GB hardware Rp <?= formatNumber((float) ($investmentCost['hardwareCostIdr'] ?? 0), 0); ?> amortized over <?= formatNumber((float) ($investmentCost['amortizationDays'] ?? 365), 0); ?> days + electricity <?= formatNumber((float) ($investmentCost['electricityIdrPerKwh'] ?? 0), 0); ?>/kWh at <?= formatNumber((float) ($investmentCost['powerWatt'] ?? 0), 0); ?>W (<?= formatNumber((float) ($investmentCost['kwhPerDay'] ?? 0), 3); ?> kWh/day).
			</div>
		</div>
	</div>

	<script>
		let payload = <?= json_encode($payload, JSON_UNESCAPED_SLASHES); ?>;
		const dashboardCardToggleConfig = [
			{
				cardId: 'strategyCard',
				storageKey: 'trading-v3-strategy-card-hidden',
				buttons: [
					{ id: 'toggleStrategyCard', showLabel: 'Show Strategy', hideLabel: 'Hide Strategy' },
					{ id: 'toggleStrategyCardChat', showLabel: 'Show V3 Strategy State', hideLabel: 'Hide V3 Strategy State' },
				],
			},
			{
				cardId: 'autoLearnCard',
				storageKey: 'trading-v3-auto-learn-card-hidden',
				buttons: [
					{ id: 'toggleAutoLearnCard', showLabel: 'Show DB Auto-Learn Advisor', hideLabel: 'Hide DB Auto-Learn Advisor' },
				],
			},
			{
				cardId: 'portfolioCard',
				storageKey: 'trading-v3-portfolio-card-hidden',
				buttons: [
					{ id: 'togglePortfolioCard', showLabel: 'Show Portfolio Daily Report', hideLabel: 'Hide Portfolio Daily Report' },
				],
			},
		];

		function formatPrice(num) {
			return new Intl.NumberFormat('id-ID').format(Number(num || 0));
		}

		function initDashboardCardToggles() {
			dashboardCardToggleConfig.forEach((config) => {
				const card = document.getElementById(config.cardId);
				if (!card) {
					return;
				}

				const buttonDefs = (config.buttons || []).map((buttonConfig) => {
					const button = document.getElementById(buttonConfig.id);
					if (!button) {
						return null;
					}

					return {
						button,
						showLabel: String(buttonConfig.showLabel || button.textContent || 'Show'),
						hideLabel: String(buttonConfig.hideLabel || button.textContent || 'Hide'),
					};
				}).filter(Boolean);

				if (buttonDefs.length === 0) {
					return;
				}

				const updateState = (isHidden) => {
					card.classList.toggle('is-hidden', isHidden);
					buttonDefs.forEach((buttonDef) => {
						buttonDef.button.textContent = isHidden ? buttonDef.showLabel : buttonDef.hideLabel;
						buttonDef.button.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
					});
				};

				const storedState = window.localStorage.getItem(config.storageKey);
				updateState(storedState === '1');

				buttonDefs.forEach((buttonDef) => {
					buttonDef.button.onclick = () => {
						const isHidden = !card.classList.contains('is-hidden');
						updateState(isHidden);
						window.localStorage.setItem(config.storageKey, isHidden ? '1' : '0');
					};
				});
			});
		}

		function initTradeChart() {
			const tradePoints = payload.tradeChart || [];
			const labels = tradePoints.map((t) => t.timeLabel);
			const prices = tradePoints.map((t) => Number(t.price || 0));

			new Chart(document.getElementById('tradeChart'), {
				type: 'line',
				data: {
					labels,
					datasets: [{
						label: 'Price (IDR)',
						data: prices,
						borderColor: '#22d3ee',
						backgroundColor: 'rgba(34, 211, 238, 0.16)',
						borderWidth: 2,
						tension: 0.25,
						fill: true,
						pointRadius: 2,
					}],
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: { display: true },
						tooltip: {
							callbacks: {
								label: (ctx) => 'Rp ' + formatPrice(ctx.parsed.y),
							},
						},
					},
					scales: {
						x: {
							ticks: { color: '#9ca3af', maxTicksLimit: 8 },
							grid: { color: 'rgba(148, 163, 184, 0.12)' },
						},
						y: {
							ticks: {
								color: '#9ca3af',
								callback: (value) => 'Rp ' + formatPrice(value),
							},
							grid: { color: 'rgba(148, 163, 184, 0.12)' },
						},
					},
				},
			});
		}

		function initOrderbookChart() {
			const buy = payload.orderbook && payload.orderbook.buy ? payload.orderbook.buy.slice(0, 20) : [];
			const sell = payload.orderbook && payload.orderbook.sell ? payload.orderbook.sell.slice(0, 20) : [];
			const labels = [];
			const buyValues = [];
			const sellValues = [];

			for (let i = 0; i < 20; i += 1) {
				const b = buy[i];
				const s = sell[i];
				labels.push(b && b.price ? formatPrice(b.price) : (s && s.price ? formatPrice(s.price) : '-'));
				buyValues.push(b ? Number(b.amount || 0) : 0);
				sellValues.push(s ? Number(s.amount || 0) : 0);
			}

			new Chart(document.getElementById('orderbookChart'), {
				type: 'bar',
				data: {
					labels,
					datasets: [
						{
							label: 'Buy Amount',
							data: buyValues,
							backgroundColor: 'rgba(34, 197, 94, 0.72)',
						},
						{
							label: 'Sell Amount',
							data: sellValues,
							backgroundColor: 'rgba(239, 68, 68, 0.72)',
						}
					],
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: { labels: { color: '#e5e7eb' } },
						tooltip: {
							callbacks: {
								title: (items) => items.length ? ('Price Rp ' + items[0].label) : '',
							},
						},
					},
					scales: {
						x: {
							ticks: { color: '#9ca3af', maxTicksLimit: 6 },
							grid: { color: 'rgba(148, 163, 184, 0.12)' },
						},
						y: {
							ticks: { color: '#9ca3af' },
							grid: { color: 'rgba(148, 163, 184, 0.12)' },
						},
					},
				},
			});
		}

		function initMimicChat() {
			const historyKey = 'trading-v3-mimic-chat-history';
			const maxHistory = 10;
			const form = document.getElementById('mimicChatForm');
			const input = document.getElementById('mimicPrompt');
			const result = document.getElementById('mimicResult');
			const historyEl = document.getElementById('mimicHistory');
			const clearHistoryBtn = document.getElementById('mimicClearHistoryBtn');
			const sendBtn = document.getElementById('mimicSendBtn');
			if (!form || !input || !result || !sendBtn || !historyEl || !clearHistoryBtn) {
				return;
			}

			const escapeHtml = (value) => String(value || '')
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#39;');

			const readHistory = () => {
				try {
					const raw = window.localStorage.getItem(historyKey);
					if (!raw) {
						return [];
					}
					const parsed = JSON.parse(raw);
					return Array.isArray(parsed) ? parsed : [];
				} catch (error) {
					return [];
				}
			};

			const saveHistory = (items) => {
				window.localStorage.setItem(historyKey, JSON.stringify(items.slice(0, maxHistory)));
			};

			const renderHistory = () => {
				const items = readHistory();
				if (items.length === 0) {
					historyEl.innerHTML = '<div class="muted">No history yet.</div>';
					return;
				}

				historyEl.innerHTML = items.map((item) => {
					const isError = Boolean(item && item.error);
					return '<div class="chat-history-item' + (isError ? ' error' : '') + '">' +
						'<div class="chat-history-meta">' + escapeHtml(item.timeLabel || '-') + (isError ? ' | error' : '') + '</div>' +
						'<div class="chat-history-prompt">Prompt: ' + escapeHtml(item.prompt || '') + '</div>' +
						'<div class="chat-history-reply">Reply: ' + escapeHtml(item.reply || '') + '</div>' +
					'</div>';
				}).join('');
			};

			const addHistoryEntry = (promptText, replyText, isError) => {
				const items = readHistory();
				items.unshift({
					timeLabel: new Date().toLocaleString(),
					prompt: promptText,
					reply: replyText,
					error: Boolean(isError),
				});
				saveHistory(items);
				renderHistory();
			};

			const setResult = (message, isError) => {
				result.textContent = String(message || '');
				result.classList.toggle('error', Boolean(isError));
			};

			clearHistoryBtn.addEventListener('click', () => {
				window.localStorage.removeItem(historyKey);
				renderHistory();
				setResult('History cleared.', false);
			});

			renderHistory();

			form.addEventListener('submit', async (event) => {
				event.preventDefault();
				const prompt = String(input.value || '').trim();
				const maxChars = Number(payload.mimicChat && payload.mimicChat.maxPromptChars ? payload.mimicChat.maxPromptChars : 400);
				if (!prompt) {
					setResult('Prompt is required.', true);
					return;
				}
				if (prompt.length > maxChars) {
					setResult('Prompt exceeds max length of ' + maxChars + ' chars.', true);
					return;
				}

				sendBtn.disabled = true;
				setResult('Sending...', false);
				try {
					const response = await fetch(window.location.pathname + '?action=mimic_chat', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify({ prompt }),
					});

					const rawBody = await response.text();
					let body = null;
					try {
						body = rawBody ? JSON.parse(rawBody) : null;
					} catch (parseError) {
						body = null;
					}

					if (!response.ok || !body || body.ok !== true) {
						const errorText = body && body.error ? body.error : ('HTTP ' + response.status);
						const rawDetails = !body && rawBody ? rawBody.trim().slice(0, 600) : '';
						const outputDetails = body && body.output ? String(body.output) : '';
						const detailsText = outputDetails !== '' ? outputDetails : rawDetails;
						const details = detailsText !== '' ? ('\n\n' + detailsText) : '';
						const finalError = 'Request failed: ' + errorText + details;
						setResult(finalError, true);
						addHistoryEntry(prompt, finalError, true);
						return;
					}

					const outputText = body.output ? String(body.output) : 'No output.';
					setResult(outputText, false);
					addHistoryEntry(prompt, outputText, false);
				} catch (error) {
					const finalError = 'Request failed: ' + String(error && error.message ? error.message : error);
					setResult(finalError, true);
					addHistoryEntry(prompt, finalError, true);
				} finally {
					sendBtn.disabled = false;
				}
			});
		}

		initDashboardCardToggles();
		initTradeChart();
		initOrderbookChart();
		initMimicChat();

		let remainingSeconds = Number(payload.refreshSeconds || 15);
		let countdownEl = document.getElementById('refreshCountdown');
		let refreshInFlight = false;

		function buildBaseQueryParams() {
			const params = new URLSearchParams(window.location.search);
			const pair = params.get('pair') || payload.pair || 'btcidr';
			params.set('pair', pair);
			params.delete('ajax');
			return params;
		}

		function isChatInteractionActive() {
			const chatCard = document.querySelector('.chat-card');
			const activeElement = document.activeElement;
			if (chatCard && activeElement && chatCard.contains(activeElement)) {
				return true;
			}

			const sendBtn = document.getElementById('mimicSendBtn');
			return Boolean(sendBtn && sendBtn.disabled);
		}

		function updateCountdownDisplay() {
			countdownEl = document.getElementById('refreshCountdown');
			if (countdownEl) {
				countdownEl.textContent = String(Math.max(0, remainingSeconds));
			}
		}

		async function refreshDashboardInPlace() {
			if (refreshInFlight || isChatInteractionActive()) {
				return;
			}

			refreshInFlight = true;
			try {
				const baseParams = buildBaseQueryParams();
				const htmlUrl = window.location.pathname + (baseParams.toString() ? ('?' + baseParams.toString()) : '');
				const jsonParams = new URLSearchParams(baseParams);
				jsonParams.set('ajax', '1');
				const jsonUrl = window.location.pathname + '?' + jsonParams.toString();

				const [htmlResponse, jsonResponse] = await Promise.all([
					fetch(htmlUrl, { cache: 'no-store' }),
					fetch(jsonUrl, { cache: 'no-store' }),
				]);

				if (!htmlResponse.ok || !jsonResponse.ok) {
					throw new Error('Refresh request failed');
				}

				const [htmlText, nextPayload] = await Promise.all([
					htmlResponse.text(),
					jsonResponse.json(),
				]);

				const parser = new DOMParser();
				const nextDocument = parser.parseFromString(htmlText, 'text/html');
				const currentContainer = document.querySelector('.container');
				const nextContainer = nextDocument.querySelector('.container');

				if (!currentContainer || !nextContainer) {
					throw new Error('Unable to update dashboard container');
				}

				const currentChatCard = currentContainer.querySelector('.chat-card');
				const nextChatCard = nextContainer.querySelector('.chat-card');
				if (currentChatCard && nextChatCard) {
					nextChatCard.replaceWith(currentChatCard);
				}

				currentContainer.replaceWith(nextContainer);
				payload = nextPayload;

				initDashboardCardToggles();
				initTradeChart();
				initOrderbookChart();

				remainingSeconds = Number(payload.refreshSeconds || 15);
				updateCountdownDisplay();
			} catch (error) {
				remainingSeconds = 5;
				updateCountdownDisplay();
			} finally {
				refreshInFlight = false;
			}
		}

		const countdownTimer = setInterval(() => {
			if (refreshInFlight) {
				updateCountdownDisplay();
				return;
			}

			if (isChatInteractionActive()) {
				remainingSeconds = Math.max(1, remainingSeconds);
				updateCountdownDisplay();
				return;
			}

			remainingSeconds -= 1;
			updateCountdownDisplay();
			if (remainingSeconds <= 0) {
				refreshDashboardInPlace();
			}
		}, 1000);
	</script>
</body>
</html>
