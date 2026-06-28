export type Role = "patient" | "staff";

export type Patient = {
  id: number;
  card_number: string;
  chart_number?: string;
  name: string;
  birth_date: string;
  email?: string | null;
  is_first_visit: boolean;
  has_rehab_clearance: boolean;
  is_diagnosed: boolean;
  can_book_rehab?: boolean;
  assigned_therapist_id?: number | null;
  assigned_therapist?: Therapist | null;
  reservations?: Appointment[];
  created_at?: string;
  updated_at?: string;
};

export type Staff = {
  id: number;
  staff_id: string;
  name: string;
  role?: "admin" | "staff";
  is_active?: boolean;
};

export type Shift = {
  id: number;
  staff_id: number;
  work_date: string;
  start_time: string | null;
  end_time: string | null;
  is_day_off: boolean;
  created_at?: string;
  updated_at?: string;
};

export type TreatmentType = {
  id: number;
  name: string;
};

export type Therapist = {
  id: number;
  name: string;
  specialty: string | null;
};

export type AppointmentSlot = {
  id: number | null;
  appointment_slot_id?: number | null;
  therapist_id: number;
  therapist?: Therapist | null;
  date: string;
  time?: string;
  starts_at: string;
  ends_at: string;
  capacity: number;
  max_capacity?: number;
  booked_count: number;
  status?: "available" | "full";
  available_count?: number;
  waitlist_count?: number;
  is_available: boolean;
  availability_mark: "○" | "×";
};

export type Waitlist = {
  id: number;
  patient_id: number;
  slot_id: number;
  priority: number;
  status: "waiting" | "promoted" | "expired";
  patient?: Patient | null;
  slot?: AppointmentSlot | null;
  created_at?: string;
  updated_at?: string;
};

export type Appointment = {
  id: number;
  patient: Patient;
  staff?: Staff | null;
  treatment_type?: TreatmentType | null;
  slot: AppointmentSlot;
  status: "booked" | "cancelled";
  staff_notes: string | null;
  cancelled_at?: string | null;
};
