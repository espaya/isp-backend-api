<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Payment Receipt</title>
</head>

<body style="margin:0; padding:0; background:#f6f6f6; font-family:Arial, Helvetica, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding:30px 0;">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:6px; overflow:hidden;">

                    <!-- Header -->
                    <tr>
                        <td align="center" style="padding:20px;">
                            <img src="{{ asset('logo.png') }}" alt="Logo" height="40">
                            <p style="font-size:12px; color:#666;">
                                If you have any issues with payment, contact
                                <a href="mailto:support@yourdomain.com">support@yourdomain.com</a>
                            </p>
                        </td>
                    </tr>

                    <!-- Amount -->
                    <tr>
                        <td style="background:#0b3c5d; color:#ffffff; padding:30px; text-align:center;">
                            <p style="margin:0; font-size:14px;">{{ config('app.name') }} received your payment of</p>
                            <h1 style="margin:10px 0; font-size:36px;">
                                GHS {{ number_format($payment->amount / 100, 2) }}
                            </h1>
                        </td>
                    </tr>

                    <!-- Details -->
                    <tr>
                        <td style="padding:30px;">
                            <h3 style="margin-bottom:15px;">Transaction Details</h3>

                            <table width="100%" cellpadding="8" cellspacing="0" style="font-size:14px;">
                                <tr>
                                    <td>Reference</td>
                                    <td align="right"><strong>{{ $payment->reference }}</strong></td>
                                </tr>
                                <tr>
                                    <td>Date</td>
                                    <td align="right">{{ $payment->created_at->format('jS M, Y') }}</td>
                                </tr>
                                <tr>
                                    <td>Payment Method</td>
                                    <td align="right">{{ strtoupper($payment->channel) }}</td>
                                </tr>
                                <tr>
                                    <td>Package</td>
                                    <td align="right">{{ $package->name }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding:20px; font-size:12px; color:#888;">
                            © {{ date('Y') }} {{ config('app.name') }}<br>
                            Powered by Paystack
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>

</html>