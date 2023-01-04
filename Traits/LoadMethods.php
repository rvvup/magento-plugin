<?php

namespace Rvvup\Payments\Traits;

trait LoadMethods
{
    /** @var array */
    private $template;

    /** @var array|null */
    private $processed = null;

    protected function processMethods(array $methods): array
    {
        if (!$this->processed) {
            $processed = [];
            foreach ($methods as $method) {
                $code = 'rvvup_' . $method['name'];
                $processed[$code] = $this->template;
                $processed[$code]['title'] = $method['displayName'];
                $processed[$code]['description'] = $method['description'];
                $processed[$code]['isActive'] = true;
                $processed[$code]['summaryUrl'] = $method['summaryUrl'];
                $processed[$code]['logoUrl'] = $method['logoUrl'] ?? '';
                $processed[$code]['limits'] = $this->processLimits($method['limits']['total'] ?? []);
            }
            $this->processed = $processed;
        }
        return $this->processed;
    }

    private function processLimits(array $limits): array
    {
        $processed = [];
        foreach ($limits as $limit) {
            $processed[$limit['currency']] = [
                'min' => $limit['min'],
                'max' => $limit['max'],
            ];
        }
        return $processed;
    }
}
