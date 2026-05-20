{{-- Fallback when PNG tile cannot be generated (no GD extension) --}}
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin: 0; padding: 0;">
    @for ($row = 0; $row < 8; $row++)
        <tr class="wm-fallback-row">
            @for ($col = 0; $col < 4; $col++)
                <td width="25%" style="padding: 18px 4px; text-align: center; color: #d0d0d0; font-family: Georgia, Times New Roman, serif; font-size: 16px; letter-spacing: 2px;">
                    {{ mb_strtoupper($watermark['business_name'] ?? '') }}
                </td>
            @endfor
        </tr>
    @endfor
</table>
