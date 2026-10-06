import type { HomeCarePlan, HomeCarePlanNeedArea, HomeCarePlanSummaryKey } from "../../lib/types";

// Labels, guidance, suggested phrases ("optional prompts") and starter
// templates for the Home Care Plan form. The field list mirrors
// api/app/Modules/CarePlanning/Support/HomeCarePlan.php.

export interface NeedAreaConfig {
  key: HomeCarePlanNeedArea;
  /** Short heading for the read view. */
  title: string;
  label: string;
  /** Omitted where the form doesn't ask for consent on this area. */
  consentLabel?: string;
  template: string;
}

export const NEED_AREAS: NeedAreaConfig[] = [
  {
    key: "personal_care",
    title: "Personal care",
    label: "What are the client's personal care needs?",
    consentLabel: "Has the client consented to personal care support?",
    template:
      "<p><b>Washing / bathing:</b> </p><p><b>Dressing:</b> </p><p><b>Oral care:</b> </p><p><b>Hair, shaving and nail care:</b> </p><p><b>Preferences (gender of carer, products, routine):</b> </p>",
  },
  {
    key: "continence",
    title: "Continence care",
    label: "Does the client have continence care needs?",
    consentLabel: "Has the client consented to continence care support?",
    template:
      "<p><b>Bladder:</b> </p><p><b>Bowel:</b> </p><p><b>Products used (pads, catheter, stoma):</b> </p><p><b>Toileting support and frequency:</b> </p><p><b>Skin checks:</b> </p>",
  },
  {
    key: "mobility",
    title: "Mobility",
    label: "What are the client's mobility care needs?",
    consentLabel: "Has the client consented to mobility support?",
    template:
      "<p><b>Mobility indoors:</b> </p><p><b>Mobility outdoors:</b> </p><p><b>Transfers (bed, chair, toilet):</b> </p><p><b>Equipment and number of carers:</b> </p><p><b>Falls history:</b> </p>",
  },
  {
    key: "meals",
    title: "Meals",
    label: "What are the client's meal requirements?",
    consentLabel: "Has the client consented to meal support?",
    template:
      "<p><b>Breakfast:</b> </p><p><b>Lunch:</b> </p><p><b>Evening meal:</b> </p><p><b>Drinks and fluid intake:</b> </p><p><b>Likes / dislikes:</b> </p><p><b>Support needed to eat and drink:</b> </p>",
  },
  {
    key: "medication",
    title: "Medication support",
    label: "What are the client's medication support requirements?",
    consentLabel: "Has the client consented to medication support?",
    template:
      "<p><b>Level of support (prompt / assist / administer):</b> </p><p><b>Where medication is stored:</b> </p><p><b>Who orders and collects:</b> </p><p><b>Allergies:</b> </p>",
  },
  {
    key: "support",
    title: "Support",
    label: "What are the client's support requirements?",
    template:
      "<p><b>Household tasks:</b> </p><p><b>Shopping:</b> </p><p><b>Social and community activities:</b> </p><p><b>Appointments:</b> </p>",
  },
  {
    key: "other_support",
    title: "Other support",
    label: "Other support requirements",
    consentLabel: "Has the client consented to this support?",
    template: "<p><b>Support needed:</b> </p><p><b>How and when:</b> </p>",
  },
];

export const END_OF_LIFE_AREAS: NeedAreaConfig[] = [
  {
    key: "advance_support",
    title: "Advance support",
    label: "Client advance support requirements",
    consentLabel: "Has the client consented to advance support?",
    template:
      "<p><b>Advance decisions (ADRT):</b> </p><p><b>DNACPR / ReSPECT in place:</b> </p><p><b>Lasting power of attorney:</b> </p><p><b>Preferred place of care:</b> </p>",
  },
  {
    key: "final_days",
    title: "Final days",
    label: "Client final day requirements",
    consentLabel: "Has the client consented to the final days plan?",
    template:
      "<p><b>Who they would like with them:</b> </p><p><b>Spiritual and cultural wishes:</b> </p><p><b>Comfort measures:</b> </p><p><b>Wishes after death:</b> </p>",
  },
];

export interface SummaryConfig {
  key: HomeCarePlanSummaryKey;
  label: string;
  hint?: string;
  prompts: string[];
}

export const SUMMARIES: SummaryConfig[] = [
  {
    key: "personal_needs",
    label: "Client's personal needs summary",
    hint: "One line a carer can read at the door.",
    prompts: ["Independent with personal care", "Needs prompting with personal care", "Needs assistance of one with personal care", "Needs full assistance with personal care"],
  },
  {
    key: "meal_requirements",
    label: "Client's meal requirements summary",
    prompts: ["Independent with meals", "Needs meals prepared", "Needs help to eat", "Needs prompting to eat and drink"],
  },
  {
    key: "dietary_needs",
    label: "Client's dietary needs summary",
    hint: "Include texture-modified (IDDSI) levels and allergies.",
    prompts: ["No special diet", "Diabetic diet", "Soft and bite-sized (IDDSI level 6)", "Minced and moist (IDDSI level 5)", "Pureed (IDDSI level 4)", "Thickened fluids", "Vegetarian", "Halal", "Gluten free"],
  },
  {
    key: "household_support",
    label: "Client's household support summary",
    hint: "Cleaning, laundry, shopping, bills.",
    prompts: ["No household support needed", "Light housework", "Laundry", "Shopping", "Help with post and bills"],
  },
  {
    key: "continence",
    label: "Client's continence needs summary",
    hint: "Products used and support needed.",
    prompts: ["Continent", "Occasional incontinence", "Uses pads", "Catheter in place", "Stoma in place", "Needs help to use the toilet"],
  },
  {
    key: "medication",
    label: "Client's medication summary",
    hint: "Level of support with medication.",
    prompts: ["Self-administers", "Needs prompting", "Needs assistance", "Medication administered by carers"],
  },
  {
    key: "mobility",
    label: "Client's mobility summary",
    hint: "How they move around and how many carers.",
    prompts: ["Fully mobile", "Mobile with a walking aid", "Needs assistance of one", "Needs assistance of two", "Hoisted for all transfers", "Bed bound"],
  },
  {
    key: "mobility_aids",
    label: "Client's use of aids for mobility summary",
    hint: "Equipment used to move or transfer.",
    prompts: ["No aids", "Walking stick", "Zimmer frame", "Rollator", "Wheelchair", "Stand aid", "Hoist and sling", "Slide sheets"],
  },
  {
    key: "other_support_needs",
    label: "Other support needs summary",
    prompts: ["None", "Companionship", "Support to attend appointments", "Support to access the community", "Sensory support (sight / hearing)"],
  },
];

export const ABOUT_ME_TEMPLATE =
  "<p><b>Who I am and my life history:</b> </p><p><b>Family and people important to me:</b> </p><p><b>What I enjoy:</b> </p><p><b>My daily routine:</b> </p><p><b>How best to support me:</b> </p>";

export const GOALS_TEMPLATE =
  "<p><b>What I want to achieve:</b> </p><p><b>How carers will help:</b> </p><p><b>How we will know it's working:</b> </p>";

export const COGNITION_TEMPLATE =
  "<p><b>Diagnosis:</b> </p><p><b>Memory and orientation:</b> </p><p><b>Mental capacity:</b> </p><p><b>How best to communicate:</b> </p>";

export const GOALS_PROMPTS = [
  "To remain living safely at home",
  "To maintain independence with daily living",
  "To regain mobility after a hospital stay",
  "To stay connected with family and community",
  "To be comfortable and pain free",
];

export const COGNITION_PROMPTS = [
  "No known cognitive impairment",
  "Mild memory loss",
  "Diagnosed with dementia",
  "Fluctuating capacity",
  "Lacks capacity for care decisions — best interests apply",
];

/** A copy that's safe to edit — every area and summary present. */
export function emptyHomeCarePlan(): HomeCarePlan {
  return { needs: {}, summaries: {} };
}

/** Whether a plan has anything recorded at all. */
export function homeCarePlanHasContent(plan: HomeCarePlan | null | undefined): boolean {
  if (!plan) return false;
  return Boolean(
    plan.about_me ||
      plan.desired_outcomes ||
      plan.goals_and_outcomes ||
      plan.cognitive_impairment_summary ||
      plan.cognitive_impairment ||
      Object.keys(plan.needs ?? {}).length ||
      Object.keys(plan.summaries ?? {}).length,
  );
}

export function consentLabel(consented: boolean | null | undefined): string {
  if (consented === true) return "Consent given";
  if (consented === false) return "Consent not given";
  return "Consent not recorded";
}
