import { useEffect, useRef, useState } from "react";
import { describeGeolocationError } from "../../lib/geolocation";
import { usePostLocation } from "./api";

const MIN_POST_INTERVAL_MS = 25000;

export interface LastKnownPosition {
  latitude: number;
  longitude: number;
  accuracy: number | null;
}

/**
 * Continuously posts GPS pings while `enabled` is true — driven by duty
 * check-in/check-out (see MyDayPage) rather than its own toggle, so a carer
 * is tracked for the whole shift, not just inside an active visit.
 */
export function useLocationSharing(enabled: boolean, activeVisitId: number | null) {
  const [error, setError] = useState<string | null>(null);
  const [lastKnownPosition, setLastKnownPosition] = useState<LastKnownPosition | null>(null);
  const lastPostedAt = useRef(0);
  const visitIdRef = useRef(activeVisitId);
  const postLocation = usePostLocation();
  const supported = typeof navigator !== "undefined" && "geolocation" in navigator;

  useEffect(() => {
    visitIdRef.current = activeVisitId;
  }, [activeVisitId]);

  useEffect(() => {
    if (!enabled || !supported) return;

    const watchId = navigator.geolocation.watchPosition(
      (position) => {
        setError(null);
        setLastKnownPosition({
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
          accuracy: position.coords.accuracy,
        });

        const now = Date.now();
        if (now - lastPostedAt.current < MIN_POST_INTERVAL_MS) return;
        lastPostedAt.current = now;
        postLocation.mutate({
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
          accuracy: position.coords.accuracy,
          visit_id: visitIdRef.current,
        });
      },
      (geoError) => setError(describeGeolocationError(geoError)),
      { enableHighAccuracy: true, maximumAge: 15000 },
    );

    return () => navigator.geolocation.clearWatch(watchId);
    // Deliberately excludes postLocation (a new mutation object every render)
    // and reads activeVisitId via the ref above — neither should tear down
    // and restart the GPS watch.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enabled, supported]);

  return { supported, error, lastKnownPosition };
}
