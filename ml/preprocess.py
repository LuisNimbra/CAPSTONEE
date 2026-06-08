"""
Data preprocessing for PESO DSS ML pipeline.
Converts applicant profile dicts into numeric feature vectors.
Features selected via Information Gain (see paper, Ch. III):
  1. education_encoded      — ordinal 1-5
  2. edu_meets_req          — binary
  3. years_experience       — numeric
  4. exp_meets_req          — binary
  5. skill_match_score      — 0.0-1.0
  6. skill_count            — numeric
  7. age                    — numeric (imputed to 25 if missing)
"""
import numpy as np

FEATURES = [
    'education_encoded',
    'edu_meets_req',
    'years_experience',
    'exp_meets_req',
    'skill_match_score',
    'skill_count',
    'age',
]

EDUCATION_MAP = {
    'elementary':    1,
    'high school':   2,
    'vocational':    3,
    'college':       4,
    'post-graduate': 5,
}


def encode_education(level: str) -> int:
    return EDUCATION_MAP.get((level or '').lower(), 2)


def build_feature_vector(applicant: dict) -> list:
    return [
        float(applicant.get('education_encoded', 2)),
        float(applicant.get('edu_meets_req', 0)),
        float(applicant.get('years_experience', 0)),
        float(applicant.get('exp_meets_req', 0)),
        float(applicant.get('skill_match_score', 0)),
        float(applicant.get('skill_count', 0)),
        float(applicant.get('age', 25) or 25),
    ]


def build_feature_matrix(applicants: list) -> np.ndarray:
    return np.array([build_feature_vector(a) for a in applicants])
