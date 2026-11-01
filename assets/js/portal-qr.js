(() => {
  const params = new URLSearchParams(window.location.search);
  const buildingSelect = document.querySelector('#building');
  const buildingCode = document.querySelector('#building-code');
  const roomInput = document.querySelector('#room');
  const roomCode = document.querySelector('#room-code');
  const roomContext = document.querySelector('#room-context');
  const banner = document.querySelector('#qr-detected');
  const locationName = document.querySelector('#qr-location-name');
  if (!buildingSelect || !roomInput || !banner) return;

  const roomValue = params.get('room_code') || params.get('room') || params.get('room_id');
  if (!roomValue) return;

  const normalize = (value) => value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]/g, '');

  const roomName = params.get('room_name') || params.get('room') || roomValue;
  const buildingValue = params.get('building_code') || params.get('building') || params.get('building_name') || roomValue.split(/[.-]/)[0];
  let buildingOption = [...buildingSelect.options].find((option) =>
    normalize(option.value) === normalize(buildingValue) || normalize(option.textContent) === normalize(buildingValue)
  );
  if (!buildingOption && buildingValue && params.get('building_name')) {
    buildingOption = document.createElement('option');
    buildingOption.value = buildingValue;
    buildingOption.textContent = params.get('building_name');
    buildingSelect.append(buildingOption);
  }

  roomInput.value = roomName;
  roomInput.readOnly = true;
  roomInput.classList.add('qr-prefilled');
  if (roomCode) roomCode.value = params.get('room_code') || params.get('room_id') || roomValue;
  if (roomContext) {
    roomContext.textContent = `M\u00e3 ph\u00f2ng ${params.get('room_code') || roomValue} \u00b7 V\u1ecb tr\u00ed nh\u1eadn t\u1eeb m\u00e3 QR`;
    roomContext.hidden = false;
  }

  if (buildingOption && buildingOption.value) {
    buildingSelect.value = buildingOption.value;
    buildingSelect.disabled = true;
    buildingSelect.classList.add('qr-prefilled');
    if (buildingCode) buildingCode.value = buildingOption.value;
  }

  const buildingName = buildingOption?.textContent.trim() || params.get('building_name') || '';
  locationName.textContent = [buildingName, roomName].filter(Boolean).join(' · ');
  banner.hidden = false;
})();
