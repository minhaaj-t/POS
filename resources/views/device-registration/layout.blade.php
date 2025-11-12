<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Device Configuration</title>
        <style>
            :root {
                color-scheme: light dark;
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            }

            body {
                margin: 0;
                background: #f4f5f7;
                color: #1c1d21;
                display: flex;
                min-height: 100vh;
                align-items: center;
                justify-content: center;
            }

            .container {
                background: #ffffff;
                padding: 2.5rem;
                border-radius: 16px;
                box-shadow:
                    0 20px 60px rgba(15, 23, 42, 0.12),
                    0 8px 20px rgba(15, 23, 42, 0.08);
                width: min(480px, 92vw);
            }

            h1 {
                margin: 0 0 0.5rem;
                font-size: 1.75rem;
                font-weight: 600;
            }

            p.description {
                margin: 0 0 1.5rem;
                color: #475467;
            }

            form {
                display: grid;
                gap: 1.25rem;
            }

            label {
                display: block;
                font-weight: 600;
                font-size: 0.95rem;
                margin-bottom: 0.35rem;
                color: #1c1d21;
            }

            input,
            textarea {
                width: 100%;
                padding: 0.85rem 0.95rem;
                border-radius: 10px;
                border: 1px solid #d0d5dd;
                font-size: 1rem;
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
                background-color: #f9fafb;
            }

            input:focus,
            textarea:focus {
                border-color: #2563eb;
                outline: none;
                box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
                background-color: #ffffff;
            }

            input[readonly] {
                background: #f1f5f9;
                cursor: not-allowed;
            }

            textarea {
                min-height: 120px;
                resize: vertical;
            }

            .actions {
                display: flex;
                justify-content: space-between;
                gap: 1rem;
                margin-top: 0.5rem;
            }

            .btn {
                flex: 1;
                display: inline-flex;
                justify-content: center;
                align-items: center;
                padding: 0.85rem 1rem;
                border-radius: 10px;
                border: none;
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                transition: transform 0.15s ease, box-shadow 0.15s ease;
            }

            .btn-primary {
                background: linear-gradient(135deg, #2563eb, #1d4ed8);
                color: #ffffff;
                box-shadow: 0 10px 20px rgba(37, 99, 235, 0.25);
            }

            .btn-secondary {
                background: #e2e8f0;
                color: #1c1d21;
            }

            .btn:focus-visible {
                outline: 3px solid rgba(37, 99, 235, 0.35);
                outline-offset: 2px;
            }

            .btn:hover {
                transform: translateY(-1px);
            }

            .stage-indicator {
                display: flex;
                gap: 0.5rem;
                margin-bottom: 1.75rem;
                font-size: 0.85rem;
                color: #64748b;
                align-items: center;
            }

            .stage-indicator span {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 1.75rem;
                height: 1.75rem;
                border-radius: 50%;
                border: 2px solid #d0d5dd;
                font-weight: 600;
                background: #ffffff;
            }

            .stage-indicator span.active {
                border-color: #2563eb;
                color: #2563eb;
                box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.18);
            }

            .stage-indicator span.completed {
                background: #2563eb;
                color: #ffffff;
                border-color: #2563eb;
            }

            .error {
                color: #dc2626;
                margin-top: 0.35rem;
                font-size: 0.85rem;
            }

            @media (max-width: 540px) {
                .container {
                    padding: 1.75rem;
                }

                .actions {
                    flex-direction: column-reverse;
                }

                .btn {
                    width: 100%;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="stage-indicator">
                @foreach ($stages as $stageNumber => $stageLabel)
                    <span @class([
                        'active' => $currentStage === $stageNumber,
                        'completed' => $stageNumber < $currentStage,
                    ])>{{ $stageNumber }}</span>
                @endforeach
            </div>

            @yield('content')
        </div>

        @yield('scripts')
    </body>
</html>

