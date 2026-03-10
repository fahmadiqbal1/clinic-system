<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription #{{ $prescription->id }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        .container {
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #059669;
        }
        .clinic-info {
            text-align: left;
        }
        .clinic-name {
            font-size: 20px;
            font-weight: bold;
            color: #047857;
        }
        .doctor-info {
            text-align: right;
        }
        .rx-symbol {
            font-size: 36px;
            color: #047857;
            font-weight: bold;
            margin: 20px 0;
        }
        .patient-info {
            background-color: #f0fdf4;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .patient-info-row {
            display: flex;
            margin-bottom: 5px;
        }
        .patient-label {
            font-weight: bold;
            width: 100px;
            color: #4b5563;
        }
        .diagnosis-section {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
        }
        .diagnosis-label {
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
            color: #92400e;
        }
        .medications-section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #047857;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #d1fae5;
        }
        .medication-item {
            padding: 10px;
            margin-bottom: 10px;
            background-color: #f9fafb;
            border-left: 3px solid #3b82f6;
        }
        .medication-name {
            font-weight: bold;
            font-size: 13px;
            color: #1f2937;
        }
        .medication-details {
            font-size: 11px;
            color: #6b7280;
            margin-top: 5px;
        }
        .medication-instructions {
            font-size: 10px;
            color: #4b5563;
            margin-top: 5px;
            font-style: italic;
        }
        .notes-section {
            margin-top: 20px;
            padding: 15px;
            background-color: #f3f4f6;
            border-radius: 5px;
        }
        .signature-section {
            margin-top: 40px;
            text-align: right;
        }
        .signature-line {
            width: 200px;
            border-top: 1px solid #333;
            margin-left: auto;
            margin-top: 40px;
            padding-top: 5px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="clinic-info">
                <div class="clinic-name">Aviva HealthCare</div>
                <div>Excellence in Medical Care</div>
            </div>
            <div class="doctor-info">
                <strong>Dr. {{ $prescription->doctor->name }}</strong><br>
                <span style="font-size: 10px;">{{ $prescription->created_at->format('d M Y') }}</span>
            </div>
        </div>

        <div class="rx-symbol">℞</div>

        <div class="patient-info">
            <div class="patient-info-row">
                <span class="patient-label">Patient:</span>
                <span>{{ $prescription->patient->full_name }}</span>
            </div>
            @if($prescription->patient->date_of_birth)
            <div class="patient-info-row">
                <span class="patient-label">Age:</span>
                <span>{{ $prescription->patient->date_of_birth->age }} years</span>
            </div>
            @endif
            <div class="patient-info-row">
                <span class="patient-label">Gender:</span>
                <span>{{ ucfirst($prescription->patient->gender) }}</span>
            </div>
        </div>

        @if($prescription->diagnosis)
        <div class="diagnosis-section">
            <div class="diagnosis-label">Diagnosis</div>
            <div>{{ $prescription->diagnosis }}</div>
        </div>
        @endif

        <div class="medications-section">
            <div class="section-title">Prescribed Medications</div>
            @foreach($prescription->items as $index => $item)
            <div class="medication-item">
                <div class="medication-name">{{ $index + 1 }}. {{ $item->medication_name }}</div>
                <div class="medication-details">
                    @if($item->dosage)
                        <strong>Dosage:</strong> {{ $item->dosage }}
                    @endif
                    @if($item->frequency)
                        &nbsp;|&nbsp; <strong>Frequency:</strong> {{ $item->frequency }}
                    @endif
                    @if($item->duration)
                        &nbsp;|&nbsp; <strong>Duration:</strong> {{ $item->duration }}
                    @endif
                    @if($item->quantity)
                        &nbsp;|&nbsp; <strong>Qty:</strong> {{ $item->quantity }}
                    @endif
                </div>
                @if($item->instructions)
                <div class="medication-instructions">
                    Instructions: {{ $item->instructions }}
                </div>
                @endif
            </div>
            @endforeach
        </div>

        @if($prescription->notes)
        <div class="notes-section">
            <strong>Additional Notes:</strong><br>
            {{ $prescription->notes }}
        </div>
        @endif

        <div class="signature-section">
            <div class="signature-line">
                Dr. {{ $prescription->doctor->name }}<br>
                <span style="font-size: 10px;">Signature</span>
            </div>
        </div>

        <div class="footer">
            <p>This prescription is valid for 7 days from the date of issue.</p>
            <p>Keep medicines out of reach of children.</p>
            <p>Aviva HealthCare | Excellence in Medical Care</p>
        </div>
    </div>
</body>
</html>
