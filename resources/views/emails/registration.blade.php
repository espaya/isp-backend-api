<!DOCTYPE html>

<html>

<head>
    <meta charset="UTF-8">
    <title>Welcome to RM Novanet</title>
</head>

<body style="margin:0; padding:0; background-color:#f4f6f8; font-family: Arial, Helvetica, sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f8; padding:20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.05);">

                    ```
                    <!-- Header -->
                    <tr>
                        <td style="background:#0d6efd; padding:24px; text-align:center;">
                            <h1 style="color:#ffffff; margin:0; font-size:24px;">
                                RM Novanet
                            </h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:30px; color:#333;">
                            <h2 style="margin-top:0;">Welcome, {{ ucfirst($user->name) }} 👋</h2>

                            <p style="font-size:15px; line-height:1.6;">
                                Thank you for registering with <strong>RM Novanet</strong>.
                                Your account has been successfully created, and you can now access
                                your dashboard and manage your services with ease.
                            </p>

                            <p style="font-size:15px; line-height:1.6;">
                                Here’s what you can do next:
                            </p>

                            <ul style="font-size:15px; line-height:1.6; padding-left:20px;">
                                <li>View and manage your account</li>
                                <li>Monitor payments and subscriptions</li>
                                <li>Access support whenever you need it</li>
                            </ul>

                            <!-- CTA Button -->
                            <div style="text-align:center; margin:30px 0;">
                                <a href="{{ dashboard_url }}"
                                    style="background:#0d6efd; color:#ffffff; text-decoration:none;
                      padding:12px 24px; border-radius:5px; font-size:15px;">
                                    Go to Dashboard
                                </a>
                            </div>

                            <p style="font-size:14px; color:#555;">
                                If you did not create this account, please ignore this email or contact our support team immediately.
                            </p>

                            <p style="font-size:14px; margin-top:30px;">
                                Best regards,<br>
                                <strong>RM Novanet Team</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background:#f1f3f5; padding:15px; text-align:center; font-size:12px; color:#777;">
                            © {{ date('Y') }} RM Novanet. All rights reserved.<br>
                            Need help? Contact us at <a href="mailto:support@yourdomain.com">support@yourdomain.com</a>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
        ```

    </table>

</body>

</html>