<li data-theme="{{ $theme_action }}" data-corners="false" data-shadow="false" data-iconshadow="true" data-icon="{{ (($selected_theme == $key) ? 'check' : 'minus') }}" data-inset="true" data-iconpos="right">
        <a href="" data-id="changeTheme" data-item-name="{{ $value }}" data-item-id="{{ $key }}" data-theme="{{ $theme_action }}" class="ui-link-inherit">{{ $value }}</a>
</li>
