import { useState } from "react";
import { DivIcon } from "leaflet";
import { MapContainer, Marker, Polyline, Popup, TileLayer } from "react-leaflet";
import {
  Card,
  CardBody,
  CardHeader,
  EmptyState,
  FormField,
  Input,
  PageHeader,
  Select,
} from "../../../design-system";
import { useRoute } from "../api";
import { useStaff } from "../../staff/api";
import { todayIso } from "../../../lib/dates";

function numberedIcon(index: number): DivIcon {
  return new DivIcon({
    html: `<div style="background:#00b4a3;color:white;border-radius:9999px;width:26px;height:26px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;border:2px solid white;box-shadow:0 1px 3px rgba(0,0,0,0.3);">${index + 1}</div>`,
    className: "",
    iconSize: [26, 26],
    iconAnchor: [13, 13],
  });
}

function haversineMeters(lat1: number, lng1: number, lat2: number, lng2: number): number {
  const R = 6371000;
  const toRad = (deg: number) => (deg * Math.PI) / 180;
  const dLat = toRad(lat2 - lat1);
  const dLng = toRad(lng2 - lng1);
  const a =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

export function RoutePage() {
  const [carerId, setCarerId] = useState<number | "">("");
  const [date, setDate] = useState(todayIso);
  const { data: staff } = useStaff(1);
  const { data: routeData, isLoading } = useRoute(carerId || null, date);

  const stops = routeData?.stops ?? [];
  const center: [number, number] = stops.length > 0 ? [stops[0].latitude, stops[0].longitude] : [-17.8252, 31.0335];

  return (
    <div>
      <PageHeader title="Route Planning" description="A carer's visits for the day, in order." />

      <Card className="mb-4">
        <CardBody>
          <div className="flex flex-wrap gap-4">
            <FormField label="Carer" htmlFor="route-carer">
              <Select
                id="route-carer"
                value={carerId}
                onChange={(e) => setCarerId(e.target.value ? Number(e.target.value) : "")}
              >
                <option value="">Select a carer</option>
                {(staff?.data ?? []).map((s) => (
                  <option key={s.id} value={s.user_id}>
                    {s.name}
                  </option>
                ))}
              </Select>
            </FormField>
            <FormField label="Date" htmlFor="route-date">
              <Input id="route-date" type="date" value={date} onChange={(e) => setDate(e.target.value)} />
            </FormField>
          </div>
        </CardBody>
      </Card>

      {!carerId ? (
        <EmptyState message="Select a carer and date to see their route." />
      ) : isLoading ? (
        <Card>
          <CardBody>Loading…</CardBody>
        </Card>
      ) : stops.length === 0 ? (
        <EmptyState message="No visits with a known address for this carer on this date." />
      ) : (
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_1.3fr]">
          <Card>
            <CardHeader>Stops</CardHeader>
            <CardBody>
              <ol className="space-y-3">
                {stops.map((stop, index) => {
                  const next = stops[index + 1];
                  const distance = next
                    ? haversineMeters(stop.latitude, stop.longitude, next.latitude, next.longitude)
                    : null;
                  return (
                    <li key={stop.visit_id} className="text-sm">
                      <div className="flex items-center gap-2">
                        <span className="flex h-6 w-6 shrink-0 items-center justify-center bg-teal text-xs font-bold text-white">
                          {index + 1}
                        </span>
                        <span className="font-medium text-ink">{stop.label}</span>
                        <span className="text-inksoft">{stop.start_time}</span>
                      </div>
                      {distance !== null && (
                        <div className="ml-8 mt-1 text-xs text-inksoft">
                          ~{(distance / 1000).toFixed(1)} km to next stop (~
                          {Math.max(1, Math.round(distance / 1000 / 30 * 60))} min at 30 km/h)
                        </div>
                      )}
                    </li>
                  );
                })}
              </ol>
            </CardBody>
          </Card>

          <Card className="overflow-hidden p-0">
            <MapContainer center={center} zoom={13} style={{ height: "500px", width: "100%" }}>
              <TileLayer
                attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
              />
              {stops.map((stop, index) => (
                <Marker key={stop.visit_id} position={[stop.latitude, stop.longitude]} icon={numberedIcon(index)}>
                  <Popup>
                    {index + 1}. {stop.label} — {stop.start_time}
                  </Popup>
                </Marker>
              ))}
              <Polyline positions={stops.map((s) => [s.latitude, s.longitude])} color="#00b4a3" />
            </MapContainer>
          </Card>
        </div>
      )}
    </div>
  );
}
