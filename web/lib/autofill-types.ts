export type ReusableAnswer = {
  id: number;
  key: string;
  label: string;
  category: string;
  valueSource: 'STATIC' | 'PROFILE';
  profilePath?: string | null;
  answerType: 'TEXT' | 'NUMBER' | 'BOOLEAN' | 'CHOICE' | 'MULTI_CHOICE';
  answerFr?: string | null;
  answerEn?: string | null;
  questionPatterns: {
    fr: string[];
    en: string[];
  };
  enabled: boolean;
  sensitive: boolean;
  autoFillAllowed: boolean;
  createdAt: string;
  updatedAt: string;
};

export type ResolvedReusableAnswer = ReusableAnswer & {
  resolved: {
    fr?: string | null;
    en?: string | null;
  };
  eligibleForAutomaticFill: boolean;
};

export type ResolvedReusableAnswerPayload = {
  schemaVersion: number;
  answers: ResolvedReusableAnswer[];
};

export type ReusableAnswerMatch = {
  score: number;
  matchedPattern: string;
  answer: ResolvedReusableAnswer;
};

export type ReusableAnswerMatchPayload = {
  schemaVersion: number;
  question: string;
  language: 'fr' | 'en';
  matches: ReusableAnswerMatch[];
};
