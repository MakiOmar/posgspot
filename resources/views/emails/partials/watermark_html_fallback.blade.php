{{-- Fallback when PNG tile cannot be generated (no GD extension) --}}
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin: 0; padding: 0;">
    @for ($row = 0; $row < 4; $row++)
        <tr class="wm-fallback-row">
            @for ($col = 0; $col < 3; $col++)
                <td width="33%" style="padding: 28px 8px; text-align: center; color: #d0d0d0; font-family: Georgia, Times New Roman, serif; font-size: 20px; letter-spacing: 3px;">
                    {{ mb_strtoupper($watermark['business_name'] ?? '') }}
                </td>
            @endfor
        </tr>
    @endfor
</table>
