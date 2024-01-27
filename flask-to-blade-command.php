<?php
/* this should be packetized at time */


namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ConvertFlaskViewsToBlade extends Command
{
    public array $variables;

    public array $functions;

    protected $signature = 'convert:flask-to-blade {rootDir}';

    protected $description = 'Convert Flask view templates to Laravel Blade templates';

    public string $rootDir;

    public function handle(): void
    {
        $this->rootDir = $this->argument('rootDir');
        $finder = new Finder();
        $finder->files()->in($this->rootDir);

        foreach ($finder as $file) {
            $this->convertFile($file);
        }
    }

    private function convertFile(SplFileInfo $file): void
    {
        $filePath = $file->getRealPath();
        $relativePath = str_replace($this->rootDir, '', $filePath);
        $bladePath = storage_path('flask_converted/' . basename($this->rootDir) . $relativePath);

        $content = File::get($filePath);
        $convertedContent = $this->convertToBlade($content);
        File::ensureDirectoryExists(dirname($bladePath));
        File::put($bladePath, $convertedContent);

        $this->info("Converted: $filePath -> $bladePath");
        $this->info('Variables: ');
        $this->info('functions: ');
        // Optionally, detect and report variables to be manually added
    }

    private function convertToBlade(string $content): string
    {
        // Initialize arrays to store variables and functions
        $this->variables = [];
        $this->functions = [];

        // Extract Flask variables (e.g., {{ variable_name }}) and add to $variables array
        preg_match_all('/{{\s*([\w]+)\s*}}/', $content, $matches);
        $variables = array_unique($matches[1]);

        // Extract Flask functions (e.g., flask_function()) and add to $functions array
        // Adjust this regex according to the syntax of your functions in Flask templates
        preg_match_all('/\{\{\s*([\w]+)\(\)\s*\}\}/', $content, $functionMatches);
        $functions = array_unique($functionMatches[1]);

        // Convert Flask's {% ... %} to Blade's @...
        $convertedContent = preg_replace('/{%\s*(\w+)\s*%}/', '@$1', $content);

        // Handle Flask's {% extends 'base.html' %} to Blade's @extends('base')
        $convertedContent = preg_replace("/{%\s*extends\s*'([\w\.]+)'\s*%}/", "@extends('$1')", $convertedContent);

        // Convert Flask's {% block content %} to Blade's @section('content')
        // and {% endblock %} to @endsection
        $convertedContent = preg_replace('/{%\s*block\s*(\w+)\s*%}/', "@section('$1')", $convertedContent);
        $convertedContent = str_replace('{% endblock %}', '@endsection', $convertedContent);

        // Convert Flask's {% include 'file.html' %} to Blade's @include('file')
        $convertedContent = preg_replace("/{%\s*include\s*'([\w\.]+)'\s*%}/", "@include('$1')", $convertedContent);

        // Convert Flask's {% if ... %} to Blade's @if(...)
        $convertedContent = preg_replace('/{%\s*if\s*(.+?)\s*%}/', '@if($1)', $convertedContent);
        $convertedContent = str_replace('{% endif %}', '@endif', $convertedContent);

        // Flask's autoescape to Blade's {!! !!} for raw output
        $convertedContent = str_replace('{% autoescape false %}', '{!!', $convertedContent);
        $convertedContent = str_replace('{% endautoescape %}', '!!}', $convertedContent);

        // Handle variables {{ var }}. This remains the same in Blade, so no conversion needed.
        // But you can add logic here if needed.
        // Wrap all variable usages with @isset(...)
        foreach ($variables as $variable) {
            $convertedContent = str_replace("{{ $variable }}", "@isset(\$$variable) {{\$$variable}} @endisset", $convertedContent);
        }

        // Create the comment block with variables and functions
        $commentBlock = "<?php\n/**\n  Converted from flask.\n  These variables must be filled with data:\n";
        foreach ($variables as $variable) {
            $commentBlock .= "  - $variable\n";
        }
        $commentBlock .= "\n  These functions are called:\n";
        foreach ($functions as $function) {
            $commentBlock .= "  - $function\n";
        }
        $commentBlock .= "*/\n?>\n";

        // Prepend the comment block to the converted content
        return $commentBlock . $convertedContent;
    }
}
