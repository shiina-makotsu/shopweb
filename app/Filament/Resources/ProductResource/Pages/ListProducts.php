<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Services\AdminActivityLogger;
use App\Services\CsvImportService;
use App\Support\Url;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importCsv')
                ->label('导入 SKU CSV')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->form([
                    FileUpload::make('file')
                        ->label('CSV 文件')
                        ->disk('local')
                        ->directory('imports')
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/csv',
                            'application/vnd.ms-excel',
                        ])
                        ->maxSize(5120)
                        ->required(),
                ])
                ->action(function (array $data, CsvImportService $importer, AdminActivityLogger $activity): void {
                    $path = $this->uploadedPath($data['file'] ?? null);
                    $result = $importer->importProducts(Storage::disk('local')->path($path), Auth::id());

                    $activity->log('products_csv_imported', null, '商品/SKU CSV 导入', [
                        'file' => $path,
                        'result' => $result,
                    ]);

                    Notification::make()
                        ->title('SKU CSV 导入完成')
                        ->body($this->summary($result))
                        ->success()
                        ->send();
                }),
            Action::make('exportCsv')
                ->label('导出 SKU CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(Url::route('admin.exports.products'), true),
            CreateAction::make(),
        ];
    }

    private function uploadedPath(mixed $file): string
    {
        if (is_array($file)) {
            $file = reset($file);
        }

        return (string) $file;
    }

    /**
     * @param  array{processed:int,created:int,updated:int,skipped:int,errors:array<int,string>}  $result
     */
    private function summary(array $result): string
    {
        $summary = "处理 {$result['processed']} 行，新建 {$result['created']}，更新 {$result['updated']}，跳过 {$result['skipped']}。";
        $errors = array_slice($result['errors'], 0, 3);

        if ($errors !== []) {
            $summary .= "\n".implode("\n", $errors);
        }

        return $summary;
    }
}
