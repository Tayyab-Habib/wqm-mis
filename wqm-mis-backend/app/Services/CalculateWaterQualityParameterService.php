<?php

namespace App\Services;


use App\Enums\DesiredTestEnum;
use Illuminate\Support\Collection;

class CalculateWaterQualityParameterService
{
    protected Collection $waterParametersResult;
    protected array $desiredTest;
    protected $pH;
    protected $pAlkalinity;
    protected $tAlkalinity;
    protected $t;

    public function __construct(Collection $waterParametersResult, $desiredTests)
    {
        $this->waterParametersResult = $waterParametersResult;
        $this->desiredTest = $desiredTests;
        $this->t = 0;
        $this->pH = 'NT';
        $this->pAlkalinity = 0;
        $this->tAlkalinity = 0;
    }

    private function getAnalysisResultByParameter(string $parameter): object
    {
        return $this->waterParametersResult->where('water_quality_parameter', '=', $parameter)->first();
    }

    public function calculateAnalysisResult(): Collection
    {
        if (in_array(DesiredTestEnum::Physical->value, $this->desiredTest)) {
            $this->pH = $this->getAnalysisResultByParameter('pH')['analysis_result'];
        }


        if (in_array(DesiredTestEnum::Chemical->value, $this->desiredTest)) {
            $this->pH = $this->getAnalysisResultByParameter('pH')['analysis_result'];
            $this->pAlkalinity = $this->calculatePAlkalinity();
            $this->tAlkalinity = $this->calculateTotalAlkalinity();
            $this->t = $this->tAlkalinity === 'NT' ? 'NT' : $this->tAlkalinity * 0.5;
            $this->calculateHardness();
            $this->calculateCalcium();
        }

        return $this->waterParametersResult->sortBy('water_quality_parameter')->transform(function ($waterParameter) {

            $analysisResult = match ($waterParameter['water_quality_parameter']) {
                'TDS' => $this->calculateTds(),
                'Magnesium' => $this->calculateMagnesium(),
                'Carbonate' => $this->calculateCarbonate(),
                'Bicarbonate' => $this->calculateBicarbonate(),
                'OH Alkalinity' => $this->calculateOHAlkalinity(),
                'Chloride' => $this->calculateChloride(),
                default => $waterParameter['analysis_result'],
            };

            return $waterParameter->merge(['analysis_result' => $analysisResult]);
        });
    }

    public function calculateHardness(): float|string
    {
        return max(0, $this->calculateParameterResult('Hardness'));
    }

    public function calculateCalcium(): float|string
    {
        return max(0, $this->calculateParameterResult('Calcium', 16));
    }

    public function calculatePAlkalinity(): float|string
    {
        if ($this->pH === 'NT') {
            return 'NT';
        }
        if ((float)$this->pH < 8.3) {
            return 0;
        }

        return max(0, $this->calculateParameterResult('P-Alkalinity'));
    }

    public function calculateTotalAlkalinity(): float|string
    {
        $result = $this->calculateParameterResult('T-Alkalinity');
        return max(0, $result);
    }

    public function calculateMagnesium(): float|string
    {
        $hardness = $this->getAnalysisResultByParameter('Hardness')['analysis_result'];
        $calcium = $this->getAnalysisResultByParameter('Calcium')['analysis_result'];
        if ($hardness === 'NT' || $calcium === 'NT') {
            return 'NT';
        }

        return max(0, round(($hardness - $calcium * 2.5) / 4.12, 2));
    }

    public function calculateCarbonate(): float|string
    {
        if ($this->pH === 'NT'
            || $this->pAlkalinity === 'NT'
            || $this->tAlkalinity === 'NT') {
            return 'NT';
        }

        if ((float)$this->pH < 8.3) {
            return 0;
        }
        if ($this->pAlkalinity < $this->t || $this->pAlkalinity === $this->t) {
            return max(0, 2 * $this->pAlkalinity);
        } elseif ($this->pAlkalinity > $this->t) {
            return max(0, 2 * ($this->tAlkalinity - $this->pAlkalinity));
        }

        return 'NT';
    }

    public function calculateBicarbonate(): float|string
    {
        if ($this->pH === 'NT') {
            return 'NT';
        }

        if ((float)$this->pH < 8.3) {
            return $this->tAlkalinity;
        }

        return 'NT';
    }

    public function calculateOHAlkalinity(): float|string
    {
        if ($this->pH === 'NT' || $this->pAlkalinity === 'NT' || $this->tAlkalinity === 'NT' || $this->pH <= 8.3) {
            return 'NT';
        }

        if ($this->pAlkalinity < $this->t || $this->pAlkalinity === $this->t) {
            return 0;
        } elseif ($this->pAlkalinity > $this->t) {
            return max(0, 2 * $this->pAlkalinity - $this->tAlkalinity);
        }

        return $this->tAlkalinity;
    }

    public function calculateChloride(): float|string
    {
        return max(0, $this->calculateParameterResult('Chloride', 18.18));
    }

    public function calculateTds(): float|string
    {
        $ecValue = $this->getAnalysisResultByParameter('E.C')['input_result'];
        return max(0, $ecValue === 'NT' ? 'NT' : $ecValue * 0.67);
    }

    public function calculateParameterResult(string $parameter, $value = 40)
    {
        $waterParameter = $this->getAnalysisResultByParameter($parameter);
        $waterParameterIndex = $this->waterParametersResult->search($waterParameter);

        $waterParameter['analysis_result'] = $waterParameter['analysis_result'] !== 'NT' ? max(0, $waterParameter['analysis_result'] * $value) : 'NT';

        $this->waterParametersResult->put($waterParameterIndex, $waterParameter);

        return $waterParameter['analysis_result'];
    }
}
