/**
 * The browser's native GeolocationPositionError isn't an `instanceof Error`,
 * so a naive `catch (err) { err instanceof Error ? err.message : fallback }`
 * always falls through to the generic fallback — a worker who denies the
 * permission prompt sees "Could not check in for work" with no indication
 * that location is the problem, let alone what to do about it. Wrapping the
 * native error in a real Error with a message tailored to the failure code
 * fixes every caller at once.
 */
export function describeGeolocationError(error: GeolocationPositionError): string {
  switch (error.code) {
    case error.PERMISSION_DENIED:
      return "Location access is turned off for this site. Enable it in your browser or device settings, then try again — check-in needs your location.";
    case error.POSITION_UNAVAILABLE:
      return "Your device couldn't get a location fix. Make sure GPS is turned on and you have a clear view of the sky, then try again.";
    case error.TIMEOUT:
      return "Getting your location took too long. Check your GPS signal and try again.";
    default:
      return "Couldn't get your location. Please try again.";
  }
}

export function getCurrentPosition(): Promise<GeolocationPosition> {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error("Geolocation is not supported by this browser."));
      return;
    }
    navigator.geolocation.getCurrentPosition(
      resolve,
      (error) => reject(new Error(describeGeolocationError(error))),
      { enableHighAccuracy: true },
    );
  });
}
