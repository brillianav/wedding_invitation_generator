=== Wedding Invitation Maker - BRILLI ===
Contributors: Brillian AV
Tags: wedding invitation, whatsapp, shortcode, invitation generator
Requires at least: 5.8
Requires PHP: 5.6
Tested up to: 6.6
Stable tag: 1.7.1
License: GPLv2 or later

Generate three styles of Indonesian and English wedding invitation messages with dynamic invitation URLs and WhatsApp send buttons.

== Features ==
* Three frontend tabs: Formal, Nonformal 1, and Nonformal 2.
* Indonesian and English message for every style.
* All six message templates can be edited from the top-level Wedding Invitation menu.
* Copy and WhatsApp actions for every generated message.
* Shared, page-specific generation history with an accessible frontend popup.
* Existing version 1.0 templates are preserved as the Formal template after upgrading.

== Shortcode ==
[brilli_wedding_invitation_maker]

Short alias:
[brilli_wedding_invitation]

== Placeholders ==
{name}
{phone}
{invitation_url}
{encoded_name}

== Default URLs ==
Indonesia: https://brillian.my.id/?to={encoded_name}
English: https://brillian.my.id/en/?to={encoded_name}

== Changelog ==

= 1.7.1 =
* Shows the clear-history control to logged-in users only.
* Enforces the same login requirement in the server-side AJAX handler so anonymous visitors cannot bypass the hidden button.

= 1.7.0 =
* Moved generation history from local browser storage to a dedicated WordPress database table.
* Shared each page's generated-name history with all visitors while continuing to exclude WhatsApp numbers.
* Added paginated history loading so every entry remains accessible without overloading the popup.
* Restricted clear-history access to administrators and secured public AJAX requests with page-bound nonces and validation.

= 1.6.0 =
* Added a frontend popup listing generated guest names and timestamps.
* Stores up to 50 history entries locally in the visitor's browser without saving WhatsApp numbers or sending data to the server.
* Added a two-step clear-history action, responsive styling, focus restoration, and cross-tab history updates.

= 1.5.0 =
* Refactored hook registration, asset handles, constants, and option sanitization for easier maintenance.
* Hardened custom URL templates to HTTP/HTTPS and stored message templates as plain multiline text.
* Added translatable frontend feedback, reliable clipboard error handling, and duplicate-initialization protection.
* Added uninstall cleanup and improved result-section semantics without changing existing settings.

= 1.4.2 =
* Automatically scrolls to the generated invitation section after successful generation.
* Uses smooth scrolling while respecting the visitor's reduced-motion preference.

= 1.4.1 =
* Added a self-hosted Plus Jakarta Sans variable font for the complete shortcode interface.
* Kept system font fallbacks and font-display swap for resilient rendering.

= 1.4.0 =
* Replaced the B × M monogram with the supplied pixel-art wedding illustration.
* Rethemed the shortcode with colors sampled from the illustration.
* Added warm wood, leaf green, sky blue, and coral accents while preserving inherited typography.

= 1.3.0 =
* Redesigned the shortcode as an editorial wedding invitation studio.
* Added a clearer guest-data step, visible validation, and privacy guidance.
* Reorganized generated links and bilingual messages for easier scanning and sharing.
* Added responsive layouts, accessible focus states, and reduced-motion support.
* Inherited all typography from the active theme or Elementor Global Fonts.

= 1.2.2 =
* Standardized template labels to Formal, Nonformal 1, and Nonformal 2 following Indonesian spelling conventions.

= 1.2.1 =
* Replaced the oversized custom admin-menu image with a size-safe WordPress Dashicon.
* Kept the BRILLI PNG logo scoped to the plugin settings header.

= 1.2.0 =
* Moved the admin page from Settings to a top-level WordPress menu.
* Redesigned the settings experience with guided sections and responsive layouts.
* Added screen-scoped admin styles and BRILLI white PNG branding.
* Added a direct settings link from the Plugins screen.

= 1.1.1 =
* Added the Formal, Nonformal 1, and Nonformal 2 frontend labels.

= 1.1.0 =
* Added three accessible message-style tabs to the shortcode.
* Added six configurable Indonesian and English templates.
* Added keyboard navigation for the frontend tabs.
* Preserved version 1.0 custom messages as Formal templates.
