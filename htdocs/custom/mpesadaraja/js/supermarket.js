(function () {
	'use strict';

	function getTerminalNumber() {
		var match = document.cookie.match(/(?:^|;\s*)takeposterminal=([^;]+)/);
		return match ? decodeURIComponent(match[1]) : 'default';
	}

	function getPreferenceKey() {
		return 'dolibarr.takepos.supermarket.' + getTerminalNumber();
	}

	function isCashierModeEnabled() {
		return document.body.classList.contains('mpesa-supermarket');
	}

	function setCashierMode(enabled) {
		var label = document.getElementById('mpesa-cashier-mode-label');
		document.body.classList.toggle('mpesa-supermarket', enabled);

		try {
			window.localStorage.setItem(getPreferenceKey(), enabled ? '1' : '0');
		} catch (error) {
			console.warn('Unable to save the TakePOS layout preference.', error);
		}

		if (label) {
			label.textContent = enabled ? label.dataset.disableLabel : label.dataset.enableLabel;
			var searchField = document.getElementById('search');
			if (searchField) {
				searchField.placeholder = enabled ? label.dataset.scanPlaceholder : label.dataset.standardPlaceholder;
			}
		}

		if (enabled) {
			focusScannerField();
		}
	}

	function focusScannerField() {
		var searchField = document.getElementById('search');
		if (searchField && !searchField.disabled && !searchField.readOnly) {
			searchField.focus();
			searchField.select();
		}
	}

	function restoreCashierMode() {
		var enabled = false;
		try {
			enabled = window.localStorage.getItem(getPreferenceKey()) === '1';
		} catch (error) {
			console.warn('Unable to read the TakePOS layout preference.', error);
		}

		setCashierMode(enabled);
	}

	window.MpesaToggleCashierMode = function () {
		setCashierMode(!isCashierModeEnabled());
	};

	document.addEventListener('DOMContentLoaded', function () {
		if (!document.body.classList.contains('bodytakepos')) {
			return;
		}

		restoreCashierMode();
		document.addEventListener('click', function (event) {
			if (isCashierModeEnabled() && event.target.closest('.div5 .wrapper2:not(.arrow)')) {
				window.setTimeout(focusScannerField, 100);
			}
		});

		var searchField = document.getElementById('search');
		if (searchField) {
			searchField.addEventListener('keydown', function (event) {
				if (isCashierModeEnabled() && event.key === 'Escape') {
					searchField.value = '';
					searchField.dispatchEvent(new Event('keyup', { bubbles: true }));
					focusScannerField();
				}
			});
		}
	});
}());
